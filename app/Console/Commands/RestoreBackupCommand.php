<?php

namespace App\Console\Commands;

use App\Application\UseCases\Backup\RestoreTenantBackupUseCase;
use App\Domain\Repositories\GodAdminRepositoryInterface;
use Illuminate\Console\Command;
use Throwable;

/**
 * Restores one tenant from a ledger entry.
 *
 * Interactive by default and never scheduled. Everything else in this feature
 * is designed to run unattended; this one replaces a customer's live data, and
 * the confirmation is part of the procedure rather than friction to be removed.
 *
 * `--no-activate` restores into the new database and stops there, leaving the
 * tenant pointed at its current one. That is the safe way to inspect what a
 * backup actually contains before deciding to switch to it.
 */
class RestoreBackupCommand extends Command
{
    protected $signature = 'backup:restore
        {backup : Backup id, from backup:list}
        {--actor= : GodAdmin email recorded as the operator in both audit trails}
        {--no-activate : Restore into a new database but leave the tenant pointed at its current one}
        {--force : Skip the confirmation prompt (for non-interactive operator scripts)}';

    protected $description = 'Restore a tenant database from a backup, into a new database';

    public function handle(
        RestoreTenantBackupUseCase $restoreBackup,
        GodAdminRepositoryInterface $godAdminRepository,
    ): int {
        $actorEmail = $this->option('actor');

        // Not optional. A restore that cannot say who ordered it is exactly
        // the kind of entry the tenant's own audit log exists to prevent.
        if (! $actorEmail) {
            $this->error('--actor is required: the operator is recorded in both the landlord and the tenant audit log.');

            return self::FAILURE;
        }

        $actor = $godAdminRepository->findByEmail($actorEmail);

        if ($actor === null) {
            $this->error("No GodAdmin found with email '{$actorEmail}'.");

            return self::FAILURE;
        }

        $activate = ! $this->option('no-activate');

        if ($activate && ! $this->option('force') && ! $this->confirm(
            'This will point the tenant at the restored database. The current one is left in place but stops being used. Continue?'
        )) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        try {
            $result = $restoreBackup->execute(
                backupId: $this->argument('backup'),
                actorId: $actor->id,
                activate: $activate,
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Restored into `%s` (%d tables). Previous database `%s` is untouched.',
            $result['restored_database'],
            $result['tables'],
            $result['previous_database'],
        ));

        if ($activate) {
            $this->warn(sprintf(
                'The tenant now reads `%s`. To roll back, point tenants.database_name at `%s` again.',
                $result['restored_database'],
                $result['previous_database'],
            ));
        } else {
            $this->line('The tenant still reads its previous database — nothing was switched.');
        }

        return self::SUCCESS;
    }
}
