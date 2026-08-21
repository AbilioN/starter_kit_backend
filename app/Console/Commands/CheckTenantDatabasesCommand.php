<?php

namespace App\Console\Commands;

use App\Application\UseCases\Tenant\RunForEachTenantUseCase;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Checks that every tenant database is reachable, and leaves the result where
 * the readiness probe can read it.
 *
 * This is deliberately NOT part of `GET /api/health/ready`. That probe runs at
 * probe frequency — opening one connection per tenant database each time is a
 * self-inflicted outage as the tenant count grows, and it would make the probe
 * slower exactly when the system is already struggling. So the expensive check
 * runs on a schedule and the probe reports its last result, including how old
 * that result is: a check that stopped running is itself a problem, and one
 * that silently reports stale "ok" is worse than none.
 */
class CheckTenantDatabasesCommand extends Command
{
    public const CACHE_KEY = 'health:tenant_databases';

    protected $signature = 'tenant:health-check {--tenant= : Only check this subdomain}';

    protected $description = 'Check every tenant database is reachable and cache the result for the readiness probe';

    public function handle(RunForEachTenantUseCase $runForEachTenant): int
    {
        try {
            $results = $runForEachTenant->execute(
                callback: function () {
                    $startedAt = microtime(true);
                    DB::connection('tenant')->select('select 1');

                    return round((microtime(true) - $startedAt) * 1000, 1);
                },
                subdomain: $this->option('tenant'),
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $failed = [];

        foreach ($results as $result) {
            if ($result['status'] === 'ok') {
                $this->line("[{$result['subdomain']}] ok ({$result['result']} ms)");
            } else {
                $failed[] = ['subdomain' => $result['subdomain'], 'error' => $result['error']];
                $this->error("[{$result['subdomain']}] UNREACHABLE: {$result['error']}");
            }
        }

        // No TTL: the probe decides what counts as too old, so an expired key
        // and a stale key would mean the same thing while reporting differently.
        Cache::forever(self::CACHE_KEY, [
            'checked_at' => now()->toISOString(),
            'total' => count($results),
            'failed' => $failed,
        ]);

        $this->newLine();
        $this->info(sprintf('%d tenant database(s) checked, %d unreachable.', count($results), count($failed)));

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
