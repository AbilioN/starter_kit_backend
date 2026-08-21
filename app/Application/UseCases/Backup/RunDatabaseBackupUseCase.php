<?php

namespace App\Application\UseCases\Backup;

use App\Domain\Entities\Backup;
use App\Domain\Entities\Tenant;
use App\Domain\Exceptions\BackupFailedException;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Services\BackupArchiverInterface;
use App\Domain\Services\DatabaseDumperInterface;
use App\Domain\Services\TenantInfrastructureResolverInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Dumps one database — a tenant's, or the landlord's when $tenant is null —
 * and writes it to that tenant's resolved destination.
 *
 * The order of operations is the design:
 *
 *  1. **Ledger row first, before the dump starts.** A process killed mid-dump
 *     otherwise leaves nothing at all behind, and "no row" is indistinguishable
 *     from "never scheduled".
 *  2. Resolve the destination *before* spending minutes on a dump that has
 *     nowhere to go.
 *  3. Dump → archive (gzip + encrypt) → upload → checksum on the ledger.
 *  4. **Every failure path marks the row failed.** A backup that fails quietly
 *     is worse than no backup: the operator believes they are covered.
 */
class RunDatabaseBackupUseCase
{
    public function __construct(
        private BackupRepositoryInterface $backupRepository,
        private TenantInfrastructureResolverInterface $infraResolver,
        private DatabaseDumperInterface $dumper,
        private BackupArchiverInterface $archiver,
        private ResolveBackupPolicyUseCase $resolvePolicy,
        private PruneBackupsUseCase $pruneBackups,
    ) {}

    public function execute(?Tenant $tenant, ?string $databaseName = null): Backup
    {
        $database = $databaseName
            ?? $tenant?->databaseName
            ?? config('database.connections.landlord.database');

        $connectionName = $tenant === null ? 'landlord' : 'tenant';
        $label = $tenant?->subdomain ?? 'landlord';

        $run = $this->backupRepository->startRun(
            tenantId: $tenant?->id,
            kind: Backup::KIND_DATABASE,
            requestId: $this->currentRequestId(),
        );

        $workDir = storage_path('app/backup-work');
        $dumpPath = "{$workDir}/{$run->id}.sql";
        $archivePath = $dumpPath.$this->archiver->extension();

        try {
            if (! is_dir($workDir) && ! mkdir($workDir, 0775, true) && ! is_dir($workDir)) {
                throw new BackupFailedException("Cannot create backup work directory '{$workDir}'.");
            }

            // Throws rather than returning null when nothing is configured —
            // the one place this feature deliberately refuses to be quiet.
            $destination = $this->infraResolver->resolveBackupConfig($tenant);

            $this->dumper->dump($database, $dumpPath, $connectionName);

            // Checked here and not only inside the dumper: an empty dump
            // recorded as a success is worse than a failure, because nothing
            // surfaces until someone tries to restore it. That guarantee has to
            // hold for every DatabaseDumperInterface implementation, not just
            // the one that happens to be bound today.
            if (! is_file($dumpPath) || filesize($dumpPath) === 0) {
                throw new BackupFailedException("The dump of '{$database}' is empty.");
            }

            $archived = $this->archiver->archive($dumpPath, $archivePath);

            $sizeBytes = (int) filesize($archivePath);
            $this->assertFitsCapacity($tenant, $sizeBytes, $label);

            $checksum = hash_file('sha256', $archivePath);
            $remotePath = $this->remotePath($destination, $label, $run->id, $this->archiver->extension());

            $disk = Storage::build($destination['config']);
            $stream = fopen($archivePath, 'rb');

            try {
                $disk->writeStream($remotePath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $backup = $this->backupRepository->markSucceeded(
                id: $run->id,
                providerId: $destination['provider_id'],
                diskName: $destination['disk_name'],
                destinationPath: $remotePath,
                sizeBytes: $sizeBytes,
                checksum: $checksum,
                isEncrypted: $archived['encrypted'],
            );

            Log::info('backup.database.ok', [
                'backup_id' => $backup->id,
                'tenant' => $label,
                'database' => $database,
                'size_bytes' => $sizeBytes,
                'destination' => $remotePath,
            ]);

            return $backup;
        } catch (Throwable $e) {
            $this->backupRepository->markFailed($run->id, $e->getMessage());

            Log::error('backup.database.failed', [
                'backup_id' => $run->id,
                'tenant' => $label,
                'database' => $database,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // The dump is plaintext customer data sitting on local disk. It is
            // deleted whether the run succeeded or not — a failed backup must
            // not leave a decrypted copy of a tenant's database behind.
            @unlink($dumpPath);
            @unlink($archivePath);
        }
    }

    /**
     * Capacity is checked against what was actually produced, not estimated
     * up front: compression ratios vary by an order of magnitude between a
     * chat-heavy tenant and a file-metadata-heavy one.
     *
     * Pruning here is limited to what policy already authorises — retention
     * expiry and oldest-first capacity, never the last surviving copy. If it
     * still does not fit, the run fails with a distinct reason rather than
     * being skipped: a tenant that outgrew its plan is a sales conversation,
     * not a disappearing backup.
     */
    private function assertFitsCapacity(?Tenant $tenant, int $incomingBytes, string $label): void
    {
        $policy = $this->resolvePolicy->execute($tenant);
        $maxMb = $policy['max_total_mb'];

        if ($maxMb === null) {
            return;
        }

        $maxBytes = $maxMb * 1024 * 1024;

        if ($incomingBytes > $maxBytes) {
            throw new BackupFailedException(
                "This backup alone ({$this->mb($incomingBytes)} MB) exceeds the plan's "
                ."{$maxMb} MB backup capacity for '{$label}'. Pruning cannot help."
            );
        }

        if ($this->backupRepository->totalStoredBytes($tenant?->id) + $incomingBytes <= $maxBytes) {
            return;
        }

        $this->pruneBackups->execute($tenant, headroomBytes: $incomingBytes);

        if ($this->backupRepository->totalStoredBytes($tenant?->id) + $incomingBytes > $maxBytes) {
            throw new BackupFailedException(
                "Backup capacity exceeded for '{$label}': {$maxMb} MB plan limit, and pruning "
                .'could not free enough room without deleting the last surviving copy.'
            );
        }
    }

    private function remotePath(array $destination, string $label, string $runId, string $extension): string
    {
        $prefix = trim((string) ($destination['config']['path_prefix'] ?? 'backups'), '/');

        return sprintf(
            '%s/%s/database/%s-%s%s',
            $prefix,
            $label,
            now()->format('Y-m-d_His'),
            $runId,
            $extension,
        );
    }

    private function mb(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 1);
    }

    /**
     * The 5.1 observability contract: the same id that ties an HTTP request to
     * its Horizon job ties a scheduled run to the lines that explain it.
     */
    private function currentRequestId(): ?string
    {
        $context = Log::sharedContext();

        return $context['request_id'] ?? null;
    }
}
