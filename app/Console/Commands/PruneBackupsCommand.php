<?php

namespace App\Console\Commands;

use App\Application\UseCases\Backup\FailStuckBackupRunsUseCase;
use App\Application\UseCases\Backup\PruneBackupsUseCase;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use Illuminate\Console\Command;
use Throwable;

/**
 * Applies retention and capacity to stored backups.
 *
 * Its own command, scheduled ahead of backup:run, so the night's dumps land in
 * space that has already been made. RunDatabaseBackupUseCase can also call the
 * same use case to make room for a specific dump, but only within what policy
 * already authorises, and never at the cost of a tenant's last surviving copy.
 */
class PruneBackupsCommand extends Command
{
    protected $signature = 'backup:prune
        {--tenant= : Only this subdomain (default: the landlord and every tenant)}';

    protected $description = 'Delete backups past their plan retention or capacity';

    public function handle(
        TenantRepositoryInterface $tenantRepository,
        PruneBackupsUseCase $pruneBackups,
        BackupRepositoryInterface $backupRepository,
        FailStuckBackupRunsUseCase $failStuckRuns,
    ): int {
        foreach ($failStuckRuns->execute() as $stuck) {
            $this->warn("[{$stuck->id}] stuck run marked failed");
        }

        $subdomain = $this->option('tenant');

        $tenants = $subdomain
            ? array_filter([$tenantRepository->findBySubdomain($subdomain)])
            : array_merge([null], $tenantRepository->findAll());

        if ($subdomain && $tenants === []) {
            $this->error("No tenant found with subdomain '{$subdomain}'.");

            return self::FAILURE;
        }

        $pruned = 0;
        $freed = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            $label = $tenant?->subdomain ?? 'landlord';

            try {
                $result = $pruneBackups->execute($tenant);
                $pruned += $result['pruned'];
                $freed += $result['freed_bytes'];
                $failed += $result['failed'];

                if ($result['pruned'] > 0 || $result['failed'] > 0) {
                    $this->line(sprintf(
                        '[%s] pruned %d, freed %s MB, %d failed',
                        $label,
                        $result['pruned'],
                        number_format($result['freed_bytes'] / 1024 / 1024, 1),
                        $result['failed'],
                    ));
                }
            } catch (Throwable $e) {
                $failed++;
                $this->error("[{$label}] FAILED: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%d backup(s) pruned, %s MB freed, %d failure(s).',
            $pruned,
            number_format($freed / 1024 / 1024, 1),
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
