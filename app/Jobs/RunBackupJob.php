<?php

namespace App\Jobs;

use App\Application\UseCases\Backup\RunDatabaseBackupUseCase;
use App\Application\UseCases\Backup\RunFilesBackupUseCase;
use App\Domain\Entities\Backup;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Domain\Services\TenantConnectionSwitcherInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one backup off the request thread.
 *
 * The GodAdmin panel's "back up now" cannot do this inline: a dump of a real
 * tenant takes minutes, and an HTTP worker holding that is a timeout for the
 * operator and a blocked php-fpm slot for everyone else.
 *
 * Not retried. A failed attempt has already written its reason to the ledger,
 * and a retry storm against a full or unreachable destination makes an incident
 * worse rather than better — the next scheduled run is the retry.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        private ?string $tenantId,
        private string $kind = Backup::KIND_DATABASE,
    ) {}

    public function handle(
        TenantRepositoryInterface $tenantRepository,
        TenantConnectionSwitcherInterface $tenantConnection,
        RunDatabaseBackupUseCase $runDatabaseBackup,
        RunFilesBackupUseCase $runFilesBackup,
    ): void {
        $tenant = $this->tenantId ? $tenantRepository->findById($this->tenantId) : null;

        if ($this->tenantId && $tenant === null) {
            return;
        }

        if ($this->kind === Backup::KIND_DATABASE) {
            // No connection switch: mysqldump connects on its own, which is
            // what lets a tenant whose Laravel connection is broken still be
            // dumped.
            $runDatabaseBackup->execute($tenant);

            return;
        }

        // Files read the tenant's own `files` table, so this one does need the
        // connection established.
        $tenantConnection->run($tenant->databaseName, fn () => $runFilesBackup->execute($tenant));
    }
}
