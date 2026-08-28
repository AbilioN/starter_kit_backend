<?php

namespace App\Application\UseCases\Backup;

use App\Domain\Entities\Backup;
use App\Domain\Entities\Tenant;
use App\Domain\Exceptions\BackupFailedException;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Services\BackupArchiverInterface;
use App\Domain\Services\TenantInfrastructureResolverInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Backs up one tenant's uploaded files.
 *
 * **This enumerates the tenant's own `files` table rather than walking a
 * storage prefix, and it has to.** StorageService::store() writes to
 * `{folder}/{uuid}.{ext}` with no tenant component in the path, so tenants that
 * share a disk — the default, whenever no `storage` provider is assigned — are
 * intermingled in one bucket with nothing to select on. Reading the table is
 * also simply more correct: it backs up exactly what that tenant's database
 * claims to own, and a row whose object is missing is itself worth reporting.
 *
 * Must be called inside that tenant's database connection (the commands go
 * through RunForEachTenantUseCase, which is what establishes it).
 *
 * First cut takes a full copy every run — no dedup between snapshots. That is
 * why plan capacity exists, and it is the honest tradeoff to make first: a
 * content-addressed store keyed on the stored UUID would avoid recopying
 * immutable objects, but its pruning has to reference-count across snapshots,
 * and getting that wrong deletes files that a surviving backup still points at.
 */
class RunFilesBackupUseCase
{
    public function __construct(
        private BackupRepositoryInterface $backupRepository,
        private TenantInfrastructureResolverInterface $infraResolver,
        private BackupArchiverInterface $archiver,
    ) {}

    public function execute(Tenant $tenant): Backup
    {
        $run = $this->backupRepository->startRun(
            tenantId: $tenant->id,
            kind: Backup::KIND_FILES,
            requestId: Log::sharedContext()['request_id'] ?? null,
        );

        $workDir = storage_path("app/backup-work/{$run->id}");
        $stageDir = "{$workDir}/files";
        $tarPath = "{$workDir}/files.tar";

        // Inside the try on purpose — see the note in RunDatabaseBackupUseCase.
        try {
            $archivePath = $tarPath.$this->archiver->extension('.tar');

            if (! is_dir($stageDir) && ! mkdir($stageDir, 0775, true) && ! is_dir($stageDir)) {
                throw new BackupFailedException("Cannot create backup work directory '{$stageDir}'.");
            }

            $destination = $this->infraResolver->resolveBackupConfig($tenant);
            $manifest = $this->stageFiles($stageDir, $tenant);

            file_put_contents(
                "{$workDir}/manifest.json",
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );

            $this->tar($workDir, $tarPath);
            $archived = $this->archiver->archive($tarPath, $archivePath);

            $sizeBytes = (int) filesize($archivePath);
            $checksum = hash_file('sha256', $archivePath);

            $prefix = trim((string) ($destination['config']['path_prefix'] ?? 'backups'), '/');
            $remotePath = sprintf(
                '%s/%s/files/%s-%s%s',
                $prefix,
                $tenant->subdomain,
                now()->format('Y-m-d_His'),
                $run->id,
                $this->archiver->extension('.tar'),
            );

            $stream = fopen($archivePath, 'rb');

            try {
                Storage::build($destination['config'])->writeStream($remotePath, $stream);
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

            Log::info('backup.files.ok', [
                'backup_id' => $backup->id,
                'tenant' => $tenant->subdomain,
                'files' => count($manifest['files']),
                'missing' => count($manifest['missing']),
                'size_bytes' => $sizeBytes,
            ]);

            return $backup;
        } catch (Throwable $e) {
            $this->backupRepository->markFailed($run->id, $e->getMessage());

            Log::error('backup.files.failed', [
                'backup_id' => $run->id,
                'tenant' => $tenant->subdomain,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    /**
     * @return array{files: array<int, array<string, mixed>>, missing: array<int, array<string, mixed>>}
     */
    private function stageFiles(string $stageDir, Tenant $tenant): array
    {
        $files = [];
        $missing = [];

        // Resolved explicitly, once, instead of leaning on Storage::disk('s3').
        // IdentifyTenant swaps filesystems.disks.s3 to the tenant's own bucket
        // per request — and there is no request here. Reading through the
        // global disk would look like it worked and quietly back up an empty
        // bucket for every tenant that has its own storage provider.
        $storageConfig = $this->infraResolver->resolveStorageConfig($tenant);
        $tenantS3 = $storageConfig ? Storage::build($storageConfig) : null;

        DB::connection('tenant')->table('files')->orderBy('id')->chunk(200, function ($rows) use ($stageDir, $tenantS3, &$files, &$missing) {
            foreach ($rows as $row) {
                $source = ($row->disk === 's3' && $tenantS3 !== null)
                    ? $tenantS3
                    : Storage::disk($row->disk);

                if (! $source->exists($row->path)) {
                    // Recorded, not thrown. A row pointing at a deleted object
                    // is a real inconsistency worth surfacing, but it must not
                    // stop the other 40,000 files from being backed up.
                    $missing[] = ['id' => $row->id, 'path' => $row->path, 'disk' => $row->disk];

                    continue;
                }

                $target = $stageDir.'/'.$row->path;
                $targetDir = dirname($target);

                if (! is_dir($targetDir)) {
                    mkdir($targetDir, 0775, true);
                }

                $in = $source->readStream($row->path);
                $out = fopen($target, 'wb');

                try {
                    stream_copy_to_stream($in, $out);
                } finally {
                    if (is_resource($in)) {
                        fclose($in);
                    }
                    if (is_resource($out)) {
                        fclose($out);
                    }
                }

                $files[] = [
                    'id' => $row->id,
                    'path' => $row->path,
                    'disk' => $row->disk,
                    'size' => $row->size,
                    'original_name' => $row->original_name,
                ];
            }
        });

        return ['files' => $files, 'missing' => $missing];
    }

    private function tar(string $workDir, string $tarPath): void
    {
        $process = new Process(['tar', '-cf', $tarPath, '-C', $workDir, 'files', 'manifest.json']);
        $process->setTimeout(config('backup.timeout_seconds'));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new BackupFailedException('tar failed: '.trim($process->getErrorOutput()));
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        // Staged files are plaintext customer data on local disk — removed
        // whether the run succeeded or failed.
        $process = new Process(['rm', '-rf', $path]);
        $process->run();
    }
}
