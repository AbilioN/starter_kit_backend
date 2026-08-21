<?php

namespace App\Jobs\Middleware;

use App\Domain\Services\TenantInfrastructureResolverInterface;
use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Re-establishes tenant context on a queue worker, mirroring what
 * IdentifyTenant does per-request. Job middleware (not a trait) so it runs
 * uniformly regardless of worker type (queue:work vs Horizon) without
 * relying on every job's handle() remembering to call it manually.
 *
 * A null $tenantId (console-dispatched test/dev jobs run outside any HTTP
 * request, where no tenant was ever resolved) is a no-op: the job runs
 * against whatever database.default already is.
 */
class EstablishTenantConnection
{
    public function __construct(private ?string $tenantId) {}

    public function handle($job, Closure $next): void
    {
        if (! $this->tenantId) {
            $next($job);

            return;
        }

        $tenant = Tenant::on('landlord')->find($this->tenantId);

        if ($tenant) {
            if (config('database.connections.tenant.database') !== $tenant->database_name) {
                config(['database.connections.tenant.database' => $tenant->database_name]);
                DB::purge('tenant');
            }

            config(['database.default' => 'tenant']);

            $this->applyInfrastructureConfig($tenant);

            // Mirrors what IdentifyTenant shares per request. The request id
            // itself is carried into the worker by ObservabilityServiceProvider.
            Log::shareContext([
                'tenant_id' => $tenant->id,
                'tenant' => $tenant->subdomain,
            ]);
        }

        $next($job);
    }

    /**
     * Same config-mutation idiom as IdentifyTenant::applyInfrastructureConfig()
     * — job middleware is instantiated manually (`new EstablishTenantConnection(...)`,
     * not resolved via the container), so the resolver is fetched via the
     * app() helper here instead of constructor injection.
     */
    private function applyInfrastructureConfig(Tenant $tenant): void
    {
        $infraResolver = app(TenantInfrastructureResolverInterface::class);
        $entity = $tenant->toEntity();

        $broadcastingConfig = $infraResolver->resolveBroadcastingConfig($entity);
        if ($broadcastingConfig) {
            config(['broadcasting.connections.pusher' => $broadcastingConfig]);
            Broadcast::forgetDrivers();
        }

        $storageConfig = $infraResolver->resolveStorageConfig($entity);
        if ($storageConfig) {
            config(['filesystems.disks.s3' => $storageConfig]);
            Storage::forgetDisk('s3');
        }
    }
}
