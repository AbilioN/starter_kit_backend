<?php

namespace App\Console\Commands;

use App\Application\UseCases\Tenant\MigrateTenantDatabasesUseCase;
use DomainException;
use Illuminate\Console\Command;

class MigrateTenantsCommand extends Command
{
    protected $signature = 'tenant:migrate
        {--tenant= : Only migrate this tenant\'s subdomain (default: every tenant)}
        {--pretend : Show which queries would run, without running them}';

    protected $description = 'Run pending migrations from database/migrations/tenant against every tenant database (or one, via --tenant)';

    public function handle(MigrateTenantDatabasesUseCase $migrateTenants): int
    {
        try {
            $results = $migrateTenants->execute(
                subdomain: $this->option('tenant'),
                pretend: (bool) $this->option('pretend'),
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($results as $result) {
            if ($result['status'] === 'ok') {
                $this->info("[{$result['subdomain']}] migrated ({$result['database']})");
            } else {
                $failed++;
                $this->error("[{$result['subdomain']}] FAILED ({$result['database']}): {$result['error']}");
            }

            if (trim($result['output']) !== '') {
                $this->line($result['output']);
            }
        }

        $this->newLine();
        $this->info(sprintf('%d tenant(s) migrated, %d failed.', count($results) - $failed, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
