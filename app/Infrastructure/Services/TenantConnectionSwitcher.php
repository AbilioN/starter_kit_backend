<?php

namespace App\Infrastructure\Services;

use App\Domain\Services\TenantConnectionSwitcherInterface;
use Illuminate\Support\Facades\DB;

/**
 * Runs a closure against one tenant's database and puts the connection back
 * afterwards.
 *
 * GodAdmin/landlord code holds no tenant connection by default: it runs
 * outside `tenant.identify` on purpose. Whenever it has to touch a specific
 * tenant's data it must do per-call what IdentifyTenant does per-request —
 * and, unlike a request, put it back. Leaving `database.default` pointed at a
 * customer's database is how the next landlord query in the same request
 * silently reads the wrong data.
 *
 * The restore is in a `finally` for the same reason: an exception mid-callback
 * must not leave the connection pointed at the tenant either.
 */
class TenantConnectionSwitcher implements TenantConnectionSwitcherInterface
{
    public function run(string $databaseName, callable $callback): mixed
    {
        $previousDatabase = config('database.connections.tenant.database');
        $previousDefault = config('database.default');
        $switched = $previousDatabase !== $databaseName;

        try {
            if ($switched) {
                config(['database.connections.tenant.database' => $databaseName]);
                DB::purge('tenant');
            }
            config(['database.default' => 'tenant']);

            return $callback();
        } finally {
            if ($switched) {
                config(['database.connections.tenant.database' => $previousDatabase]);
                DB::purge('tenant');
            }
            config(['database.default' => $previousDefault]);
        }
    }
}
