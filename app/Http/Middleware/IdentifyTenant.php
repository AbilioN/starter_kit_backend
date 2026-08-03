<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
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

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        if ($tenant->status === 'suspended') {
            return response()->json(['message' => 'Tenant suspended.'], 403);
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

        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
