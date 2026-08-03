<?php

namespace App\Jobs\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\DB;

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
        }

        $next($job);
    }
}
