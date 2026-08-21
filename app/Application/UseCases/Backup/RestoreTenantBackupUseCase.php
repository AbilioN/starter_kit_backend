<?php

namespace App\Application\UseCases\Backup;

use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Entities\Backup;
use App\Domain\Exceptions\BackupFailedException;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Domain\Services\BackupArchiverInterface;
use App\Domain\Services\DatabaseDumperInterface;
use App\Domain\Services\TenantConnectionSwitcherInterface;
use App\Domain\Services\TenantInfrastructureResolverInterface;
use App\Domain\Services\TenantProvisioningServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Restores one tenant's database from a ledger entry — without touching any
 * other tenant. Under database-per-tenant that is the entire point: restoring
 * the whole instance to answer one customer's support ticket is not an option.
 *
 * **Restores into a NEW database and then flips `tenants.database_name`.** The
 * obvious implementation — drop the live database and replay the dump into it —
 * turns a bad restore into a second outage stacked on the first, with nothing
 * left to go back to. Here the switch is one landlord UPDATE, and undoing it is
 * the same UPDATE backwards; the previous database is left in place untouched.
 *
 * It is also audited on both sides. The tenant's own log must never be silent
 * about an operator replacing their data underneath them — the same rule that
 * governs GodAdmin impersonation.
 */
class RestoreTenantBackupUseCase
{
    public function __construct(
        private BackupRepositoryInterface $backupRepository,
        private TenantRepositoryInterface $tenantRepository,
        private TenantInfrastructureResolverInterface $infraResolver,
        private TenantProvisioningServiceInterface $provisioningService,
        private TenantConnectionSwitcherInterface $tenantConnection,
        private DatabaseDumperInterface $dumper,
        private BackupArchiverInterface $archiver,
        private LogLandlordAuditUseCase $logLandlordAudit,
        private LogAuditUseCase $logTenantAudit,
    ) {}

    /**
     * @return array{restored_database: string, previous_database: string, tables: int}
     */
    public function execute(string $backupId, string $actorId, bool $activate = true): array
    {
        $backup = $this->backupRepository->findById($backupId);

        if ($backup === null) {
            throw new InvalidArgumentException("Backup '{$backupId}' not found.");
        }

        if ($backup->kind !== Backup::KIND_DATABASE || ! $backup->isRestorable()) {
            throw new InvalidArgumentException(
                "Backup '{$backupId}' is not a restorable database backup (kind={$backup->kind}, status={$backup->status})."
            );
        }

        if ($backup->tenantId === null) {
            // The landlord holds the map from subdomain to database_name, and
            // this use case rewrites exactly that map. Restoring it while the
            // application is reading it is an operator procedure with the stack
            // stopped, not something to trigger from a button.
            throw new InvalidArgumentException(
                'Landlord backups are restored manually with the stack down — see docs/09-backup-and-restore.md.'
            );
        }

        $tenant = $this->tenantRepository->findById($backup->tenantId);

        if ($tenant === null) {
            throw new InvalidArgumentException("Tenant '{$backup->tenantId}' no longer exists.");
        }

        $workDir = storage_path('app/backup-work');
        $downloadPath = "{$workDir}/restore-{$backup->id}".$this->archiver->extension();
        $sqlPath = "{$workDir}/restore-{$backup->id}.sql";
        $targetDatabase = $this->restoreDatabaseName($tenant->databaseName);

        try {
            if (! is_dir($workDir) && ! mkdir($workDir, 0775, true) && ! is_dir($workDir)) {
                throw new BackupFailedException("Cannot create backup work directory '{$workDir}'.");
            }

            // By the provider recorded on the row, not the tenant's current
            // one — the tenant may have been moved since this copy was written.
            $destination = $this->infraResolver->resolveBackupConfigById($backup->providerId);
            $disk = Storage::build($destination['config']);

            if (! $disk->exists($backup->destinationPath)) {
                throw new BackupFailedException(
                    "Backup object '{$backup->destinationPath}' is missing from its destination. "
                    .'The ledger says it exists; the bucket disagrees.'
                );
            }

            $this->download($disk->readStream($backup->destinationPath), $downloadPath);
            $this->assertChecksumMatches($backup, $downloadPath);

            $this->archiver->extract($downloadPath, $sqlPath, $backup->isEncrypted);

            $this->provisioningService->createDatabase($targetDatabase);
            $this->dumper->restore($targetDatabase, $sqlPath);

            $tables = $this->verify($targetDatabase);

            if ($activate) {
                $this->tenantRepository->update(id: $tenant->id, databaseName: $targetDatabase);
            }

            $this->audit($tenant->id, $tenant->subdomain, $actorId, $backup, $tenant->databaseName, $targetDatabase, $activate);

            Log::warning('backup.restore.completed', [
                'backup_id' => $backup->id,
                'tenant' => $tenant->subdomain,
                'previous_database' => $tenant->databaseName,
                'restored_database' => $targetDatabase,
                'activated' => $activate,
                'tables' => $tables,
            ]);

            return [
                'restored_database' => $targetDatabase,
                'previous_database' => $tenant->databaseName,
                'tables' => $tables,
            ];
        } finally {
            @unlink($downloadPath);
            // The extracted SQL is the tenant's data in the clear on local
            // disk. It goes whether the restore worked or not.
            @unlink($sqlPath);
        }
    }

    /**
     * MySQL caps identifiers at 64 characters, and a name that silently gets
     * truncated is a restore into the wrong database.
     */
    private function restoreDatabaseName(string $current): string
    {
        $suffix = '_r'.now()->format('ymdHis');

        return mb_substr($current, 0, 64 - mb_strlen($suffix)).$suffix;
    }

    private function download($stream, string $targetPath): void
    {
        $out = fopen($targetPath, 'wb');

        try {
            stream_copy_to_stream($stream, $out);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_resource($out)) {
                fclose($out);
            }
        }
    }

    /**
     * Verified before anything is written, not after: a corrupted download
     * replayed into a database produces a half-restored tenant, which is worse
     * than a failed restore because it looks finished.
     */
    private function assertChecksumMatches(Backup $backup, string $path): void
    {
        if ($backup->checksum === null) {
            return;
        }

        $actual = hash_file('sha256', $path);

        if (! hash_equals($backup->checksum, $actual)) {
            throw new BackupFailedException(
                "Checksum mismatch for backup '{$backup->id}': the stored object does not match what was written."
            );
        }
    }

    /**
     * A dump that replays without error can still be an empty database — a
     * truncated file, the wrong object. "Did mysql exit 0" is not the question;
     * "is there a schema in there" is.
     */
    private function verify(string $database): int
    {
        return $this->tenantConnection->run($database, function () use ($database) {
            $tables = DB::connection('tenant')->select('SHOW TABLES');
            $count = count($tables);

            if ($count === 0) {
                throw new BackupFailedException("Restore produced an empty database '{$database}'.");
            }

            $hasMigrations = DB::connection('tenant')
                ->table('information_schema.tables')
                ->where('table_schema', $database)
                ->where('table_name', 'migrations')
                ->exists();

            if (! $hasMigrations) {
                throw new BackupFailedException(
                    "Restored database '{$database}' has no migrations table — this does not look like a tenant database."
                );
            }

            return $count;
        });
    }

    private function audit(
        string $tenantId,
        string $subdomain,
        string $actorId,
        Backup $backup,
        string $previousDatabase,
        string $restoredDatabase,
        bool $activated,
    ): void {
        $metadata = [
            'backup_id' => $backup->id,
            'previous_database' => $previousDatabase,
            'restored_database' => $restoredDatabase,
            'activated' => $activated,
            'backup_taken_at' => $backup->finishedAt?->format(DATE_ATOM),
        ];

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: 'tenant_backup_restored',
            model: 'Tenant',
            modelId: $tenantId,
            metadata: $metadata,
        );

        // And in the tenant's own immutable log. If this write is the one that
        // fails, the operation still happened — so it must not be able to undo
        // the landlord entry above by throwing.
        try {
            $this->tenantConnection->run($restoredDatabase, function () use ($actorId, $tenantId, $metadata) {
                $this->logTenantAudit->execute(
                    userId: $actorId,
                    userType: 'GodAdmin',
                    action: 'restored',
                    modelType: 'Tenant',
                    modelId: $tenantId,
                    description: 'Database restored from backup by a platform operator',
                    tags: ['backup', 'restore'],
                    metadata: $metadata,
                );
            });
        } catch (\Throwable $e) {
            Log::error('backup.restore.tenant_audit_failed', [
                'tenant' => $subdomain,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
