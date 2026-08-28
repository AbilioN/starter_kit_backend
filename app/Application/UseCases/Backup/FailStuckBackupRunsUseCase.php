<?php

namespace App\Application\UseCases\Backup;

use App\Domain\Entities\Backup;
use App\Domain\Repositories\BackupRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * Closes ledger rows abandoned by a process that never came back.
 *
 * A row is written before the dump starts, so a killed container — a reboot, an
 * OOM, a `docker compose down` mid-sweep — leaves it on `running` for ever.
 * Nothing else ever reconciles it, and the difference matters: `running` reads
 * as work in progress, so the staleness check goes on waiting for a report that
 * will never come and alerting has nothing to say.
 *
 * Shared by backup:prune and backup:run rather than living in one of them.
 * Prune alone was not enough: it is scheduled daily and backups run hourly, so
 * a crashed run stayed "in progress" for up to a day — and on any host that is
 * simply switched off at 02:30, for ever. That is exactly how 74 rows
 * accumulated between 2026-08-21 and 2026-08-28.
 */
class FailStuckBackupRunsUseCase
{
    public function __construct(
        private BackupRepositoryInterface $backupRepository,
    ) {}

    /**
     * @return Backup[] the rows that were marked failed
     */
    public function execute(): array
    {
        // The cutoff is what separates a crash from a slow dump, so it has to
        // stay comfortably above the longest legitimate run (RunBackupJob's own
        // timeout is an hour). Too tight and this marks a live backup failed
        // while it is still writing.
        $cutoff = now()->subMinutes((int) config('backup.running_timeout_minutes'));

        $closed = [];

        foreach ($this->backupRepository->findStuckRunning($cutoff) as $stuck) {
            $closed[] = $this->backupRepository->markFailed(
                $stuck->id,
                'Abandoned: still running '.$stuck->startedAt?->diffForHumans()
                    .' after being started. The process did not finish, so no backup was produced.'
            );

            Log::warning('backup.run.abandoned', [
                'backup_id' => $stuck->id,
                'tenant_id' => $stuck->tenantId,
                'kind' => $stuck->kind,
                'started_at' => $stuck->startedAt?->toIso8601String(),
            ]);
        }

        return $closed;
    }
}
