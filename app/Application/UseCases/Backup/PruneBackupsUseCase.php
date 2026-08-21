<?php

namespace App\Application\UseCases\Backup;

use App\Domain\Entities\Backup;
use App\Domain\Entities\Tenant;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Services\TenantInfrastructureResolverInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Applies retention and capacity to one tenant's stored backups.
 *
 * This is the only part of the backup system that deletes anything, so it is
 * the only part that can turn a bug into data loss. Four rules hold it:
 *
 *  - **The most recent successful backup of a kind is never deleted**, whatever
 *    retention or capacity says. A ceiling must not be able to leave a tenant
 *    at zero copies — that is the state this whole feature exists to prevent.
 *  - **Oldest first.**
 *  - **The ledger row is only marked pruned once the object is actually gone.**
 *    Marking first and deleting after means a failed delete leaves a file
 *    nobody accounts for, quietly eating the tenant's capacity forever.
 *  - **One failure does not stop the sweep.** A single unreachable destination
 *    must not leave every later tenant unpruned.
 */
class PruneBackupsUseCase
{
    public function __construct(
        private BackupRepositoryInterface $backupRepository,
        private TenantInfrastructureResolverInterface $infraResolver,
        private ResolveBackupPolicyUseCase $resolvePolicy,
    ) {}

    /**
     * @param  int|null  $headroomBytes  room a backup about to be written needs
     * @return array{pruned: int, freed_bytes: int, failed: int}
     */
    public function execute(?Tenant $tenant, ?int $headroomBytes = null): array
    {
        $policy = $this->resolvePolicy->execute($tenant);
        $pruned = 0;
        $freed = 0;
        $failed = 0;

        foreach ([Backup::KIND_DATABASE, Backup::KIND_FILES] as $kind) {
            $candidates = $this->backupRepository->findPrunable($tenant?->id, $kind);

            // Oldest first, minus the newest — which is the one that must
            // survive both policies.
            $newest = array_pop($candidates);

            if ($newest === null) {
                continue;
            }

            $cutoff = now()->subDays($policy['retention_days']);

            foreach ($candidates as $candidate) {
                $expired = $candidate->finishedAt !== null && $candidate->finishedAt < $cutoff;

                if (! $expired && ! $this->needsRoom($tenant, $policy, $headroomBytes)) {
                    // Neither policy asks for this one to go, and everything
                    // after it is newer still.
                    break;
                }

                try {
                    $this->delete($candidate);
                    $pruned++;
                    $freed += $candidate->sizeBytes ?? 0;
                } catch (Throwable $e) {
                    $failed++;

                    Log::warning('backup.prune.failed', [
                        'backup_id' => $candidate->id,
                        'tenant' => $tenant?->subdomain ?? 'landlord',
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return ['pruned' => $pruned, 'freed_bytes' => $freed, 'failed' => $failed];
    }

    private function needsRoom(?Tenant $tenant, array $policy, ?int $headroomBytes): bool
    {
        if ($policy['max_total_mb'] === null) {
            return false;
        }

        $maxBytes = $policy['max_total_mb'] * 1024 * 1024;

        return $this->backupRepository->totalStoredBytes($tenant?->id) + (int) $headroomBytes > $maxBytes;
    }

    private function delete(Backup $backup): void
    {
        if ($backup->destinationPath !== null) {
            // By the provider recorded on the row, not the tenant's current
            // one: an old copy still lives wherever it was written.
            $destination = $this->infraResolver->resolveBackupConfigById($backup->providerId);

            Storage::build($destination['config'])->delete($backup->destinationPath);
        }

        $this->backupRepository->markPruned($backup->id);
    }
}
