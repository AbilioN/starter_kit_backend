<?php

namespace App\Http\Middleware;

use App\Domain\Services\TenantInfrastructureResolverInterface;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(
        private TenantInfrastructureResolverInterface $infraResolver,
    ) {}

    /**
     * Resolves the tenant from the request's subdomain, points the `tenant`
     * connection at its database, and flips database.default to `tenant` so
     * every model/query builder call with no explicit connection (including
     * raw DB::table() calls) resolves there automatically. Landlord models
     * are unaffected - they declare protected $connection = 'landlord'
     * explicitly.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = explode('.', $request->getHost())[0] ?? null;

        $tenant = $subdomain ? Tenant::where('subdomain', $subdomain)->first() : null;

        // Local-dev-only fallback: real subdomains need /etc/hosts or wildcard
        // DNS to resolve in a browser, which not everyone has set up. Never
        // enabled outside local/testing - letting a query param override
        // tenant resolution in production would undermine the whole point
        // of subdomain-based isolation (any client could ask for any
        // tenant's database by guessing/enumerating ?tenant=).
        if (! $tenant && app()->environment(['local', 'testing']) && $request->query('tenant')) {
            $tenant = Tenant::where('subdomain', $request->query('tenant'))->first();
        }

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        if ($tenant->status === 'suspended') {
            // `error` is a machine-readable code, distinct from `message`
            // (human copy, free to change) — lets the frontend reliably
            // detect "suspended" instead of string-matching a sentence.
            return response()->json(['message' => 'Tenant suspended.', 'error' => 'tenant_suspended'], 403);
        }

        if ($tenant->status !== 'active') {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        // Only purge (and reconnect) when the target database is actually
        // changing - purging an already-open connection mid-transaction
        // discards any uncommitted work on it, which matters for tests
        // (RefreshDatabase wraps each test in an open transaction) and is
        // simply wasted work in production when consecutive requests on a
        // long-lived worker hit the same tenant.
        if (config('database.connections.tenant.database') !== $tenant->database_name) {
            config(['database.connections.tenant.database' => $tenant->database_name]);
            DB::purge('tenant');
        }

        config(['database.default' => 'tenant']);

        $this->applyInfrastructureConfig($tenant);

        app()->instance('currentTenant', $tenant);

        // Every log line from here on says which tenant it belongs to. Under
        // database-per-tenant an error report without this is close to
        // useless — the same stack trace means something different depending
        // on whose database it ran against.
        Log::shareContext([
            'tenant_id' => $tenant->id,
            'tenant' => $tenant->subdomain,
        ]);

        return $next($request);
    }

    /**
     * Overrides the broadcasting/storage connection config with whatever
     * this tenant resolves to (its own override, or its plan's default) —
     * null means nothing's configured, so config() is left untouched and
     * the global .env-driven values apply exactly like before this feature
     * existed. Same "mutate named config + invalidate the manager's cached
     * instance" idiom already used above for the tenant DB connection.
     */
    private function applyInfrastructureConfig(Tenant $tenant): void
    {
        $entity = $tenant->toEntity();

        $broadcastingConfig = $this->infraResolver->resolveBroadcastingConfig($entity);
        if ($broadcastingConfig) {
            config(['broadcasting.connections.pusher' => $broadcastingConfig]);
            Broadcast::forgetDrivers();
        }

        $storageConfig = $this->infraResolver->resolveStorageConfig($entity);
        if ($storageConfig) {
            config(['filesystems.disks.s3' => $storageConfig]);
            Storage::forgetDisk('s3');
        }
    }
}
