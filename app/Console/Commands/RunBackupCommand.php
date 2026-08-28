<?php

namespace App\Console\Commands;

use App\Application\UseCases\Backup\FailStuckBackupRunsUseCase;
use App\Application\UseCases\Backup\ResolveBackupPolicyUseCase;
use App\Application\UseCases\Backup\RunDatabaseBackupUseCase;
use App\Application\UseCases\Backup\RunFilesBackupUseCase;
use App\Application\UseCases\Tenant\RunForEachTenantUseCase;
use App\Domain\Entities\Backup;
use App\Domain\Entities\Tenant;
use App\Domain\Exceptions\BackupFailedException;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Domain\Services\BackupArchiverInterface;
use App\Domain\Services\DatabaseDumperInterface;
use DomainException;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backs up the landlord and every tenant.
 *
 * Two different iteration strategies on purpose:
 *
 *  - **Database dumps do not switch connections.** mysqldump connects on its
 *    own and is told the database by name, so there is nothing to establish —
 *    and crucially, a tenant whose Laravel connection is broken can still be
 *    dumped. Routing this through RunForEachTenantUseCase would make the
 *    connection a prerequisite for backing up the very database that cannot be
 *    connected to.
 *  - **File backups do go through RunForEachTenantUseCase**, because they read
 *    that tenant's own `files` table.
 */
class RunBackupCommand extends Command
{
    protected $signature = 'backup:run
        {--tenant= : Only this subdomain (default: the landlord and every tenant)}
        {--kind=all : database|files|all}
        {--force : Ignore the plan frequency and back up now}';

    protected $description = 'Back up the landlord and every tenant database, and tenant files';

    public function handle(
        TenantRepositoryInterface $tenantRepository,
        BackupRepositoryInterface $backupRepository,
        ResolveBackupPolicyUseCase $resolvePolicy,
        RunDatabaseBackupUseCase $runDatabaseBackup,
        RunFilesBackupUseCase $runFilesBackup,
        RunForEachTenantUseCase $runForEachTenant,
        DatabaseDumperInterface $dumper,
        BackupArchiverInterface $archiver,
        FailStuckBackupRunsUseCase $failStuckRuns,
    ): int {
        $kind = $this->option('kind');

        if (! in_array($kind, ['all', Backup::KIND_DATABASE, Backup::KIND_FILES], true)) {
            $this->error("Unknown --kind '{$kind}'. Use database, files or all.");

            return self::FAILURE;
        }

        // Checked once, up front: without it a missing mysqldump binary shows
        // up as the same cryptic failure repeated for every tenant.
        if ($kind !== Backup::KIND_FILES && ! $dumper->isAvailable()) {
            $this->error(
                'mysqldump is not available in this container. It is not in the base PHP image — '
                .'the Dockerfile installs default-mysql-client, so app/horizon/scheduler must be REBUILT, not restarted.'
            );

            return self::FAILURE;
        }

        // Same reasoning as the dumper check above, for the other half of the
        // pipeline. Asked once, before a single ledger row exists: a key that
        // is missing for the first tenant is missing for all of them, and the
        // useful output is one line saying so rather than N identical failures
        // buried in a nightly sweep.
        try {
            $archiver->assertUsable();
        } catch (BackupFailedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Hourly, where prune only gets to this daily. A run this sweep is
        // about to replace should already read as failed, not as one still in
        // flight from four hours ago.
        foreach ($failStuckRuns->execute() as $stuck) {
            $this->warn("[{$stuck->id}] previous run abandoned — marked failed");
        }

        $subdomain = $this->option('tenant');
        $failed = 0;
        $done = 0;
        $skipped = 0;

        $tenants = $subdomain
            ? array_filter([$tenantRepository->findBySubdomain($subdomain)])
            : $tenantRepository->findAll();

        if ($subdomain && $tenants === []) {
            $this->error("No tenant found with subdomain '{$subdomain}'.");

            return self::FAILURE;
        }

        if ($kind !== Backup::KIND_FILES) {
            // The landlord first and unconditionally. Without it every tenant
            // dump is an anonymous file: nothing maps a subdomain to the
            // database it should be restored into.
            if (! $subdomain) {
                $this->runOne('landlord', fn () => $runDatabaseBackup->execute(null), $done, $failed);
            }

            foreach ($tenants as $tenant) {
                if (! $this->isDue($tenant, Backup::KIND_DATABASE, $resolvePolicy, $backupRepository)) {
                    $skipped++;
                    $this->line("[{$tenant->subdomain}] database: not due yet");

                    continue;
                }

                $this->runOne(
                    "{$tenant->subdomain} database",
                    fn () => $runDatabaseBackup->execute($tenant),
                    $done,
                    $failed,
                );
            }
        }

        if ($kind !== Backup::KIND_DATABASE) {
            $results = $this->backupFiles($runForEachTenant, $runFilesBackup, $resolvePolicy, $backupRepository, $subdomain, $skipped);

            foreach ($results as $result) {
                if ($result['status'] === 'ok') {
                    if ($result['result'] !== null) {
                        $done++;
                        $this->line("[{$result['subdomain']}] files: ok");
                    }
                } else {
                    $failed++;
                    $this->error("[{$result['subdomain']}] files: FAILED: {$result['error']}");
                }
            }
        }

        $this->newLine();
        $this->info(sprintf('%d backup(s) written, %d skipped, %d failed.', $done, $skipped, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function backupFiles(
        RunForEachTenantUseCase $runForEachTenant,
        RunFilesBackupUseCase $runFilesBackup,
        ResolveBackupPolicyUseCase $resolvePolicy,
        BackupRepositoryInterface $backupRepository,
        ?string $subdomain,
        int &$skipped,
    ): array {
        try {
            return $runForEachTenant->execute(
                callback: function (Tenant $tenant) use ($runFilesBackup, $resolvePolicy, $backupRepository, &$skipped) {
                    if (! $this->isDue($tenant, Backup::KIND_FILES, $resolvePolicy, $backupRepository)) {
                        $skipped++;

                        return null;
                    }

                    return $runFilesBackup->execute($tenant)->id;
                },
                subdomain: $subdomain,
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return [];
        }
    }

    /**
     * Frequency is enforced here rather than by the schedule, so the schedule
     * can run hourly and each plan still gets the cadence it pays for.
     */
    private function isDue(
        Tenant $tenant,
        string $kind,
        ResolveBackupPolicyUseCase $resolvePolicy,
        BackupRepositoryInterface $backupRepository,
    ): bool {
        $policy = $resolvePolicy->execute($tenant);

        if (! $policy['enabled'] || $policy['frequency_hours'] === null) {
            return false;
        }

        if ($this->option('force')) {
            return true;
        }

        $last = $backupRepository->findLatestSuccessful($tenant->id, $kind);

        return $last?->finishedAt === null
            || $last->finishedAt < now()->subHours($policy['frequency_hours']);
    }

    private function runOne(string $label, callable $action, int &$done, int &$failed): void
    {
        try {
            $action();
            $done++;
            $this->line("[{$label}] ok");
        } catch (Throwable $e) {
            // Never rethrown: one tenant with a broken database or an
            // unreachable bucket must not stop the other 199 from being
            // backed up. The ledger row already carries the reason.
            $failed++;
            $this->error("[{$label}] FAILED: {$e->getMessage()}");
        }
    }
}
