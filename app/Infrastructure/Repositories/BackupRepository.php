<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Backup;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Models\Backup as BackupModel;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The ledger lives in the landlord, not in each tenant's database: it has to be
 * readable precisely when a tenant's own database is the broken thing, and
 * capacity has to be summed without opening one connection per tenant.
 */
class BackupRepository implements BackupRepositoryInterface
{
    public function findById(string $id): ?Backup
    {
        return BackupModel::find($id)?->toEntity();
    }

    public function findForTenant(?string $tenantId, ?string $kind = null, int $limit = 50): array
    {
        return BackupModel::query()
            ->where('tenant_id', $tenantId)
            ->when($kind !== null, fn ($query) => $query->where('kind', $kind))
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (BackupModel $backup) => $backup->toEntity())
            ->all();
    }

    public function findLatestSuccessful(?string $tenantId, string $kind): ?Backup
    {
        return BackupModel::query()
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->where('status', Backup::STATUS_OK)
            ->orderByDesc('finished_at')
            ->first()
            ?->toEntity();
    }

    public function findPrunable(?string $tenantId, string $kind): array
    {
        return BackupModel::query()
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->where('status', Backup::STATUS_OK)
            ->orderBy('finished_at')
            ->get()
            ->map(fn (BackupModel $backup) => $backup->toEntity())
            ->all();
    }

    public function totalStoredBytes(?string $tenantId): int
    {
        return (int) BackupModel::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Backup::STATUS_OK)
            ->sum('size_bytes');
    }

    public function findStuckRunning(DateTimeInterface $olderThan): array
    {
        return BackupModel::query()
            ->where('status', Backup::STATUS_RUNNING)
            ->where('started_at', '<', $olderThan)
            ->get()
            ->map(fn (BackupModel $backup) => $backup->toEntity())
            ->all();
    }

    public function startRun(?string $tenantId, string $kind, ?string $requestId = null): Backup
    {
        return BackupModel::create([
            'tenant_id' => $tenantId,
            'kind' => $kind,
            'status' => Backup::STATUS_RUNNING,
            'is_encrypted' => false,
            'started_at' => now(),
            'request_id' => $requestId,
        ])->toEntity();
    }

    public function markSucceeded(
        string $id,
        ?string $providerId,
        string $diskName,
        string $destinationPath,
        int $sizeBytes,
        string $checksum,
        bool $isEncrypted,
    ): Backup {
        $backup = BackupModel::findOrFail($id);

        $backup->update([
            'status' => Backup::STATUS_OK,
            'provider_id' => $providerId,
            'disk_name' => $diskName,
            'destination_path' => $destinationPath,
            'size_bytes' => $sizeBytes,
            'checksum' => $checksum,
            'is_encrypted' => $isEncrypted,
            'finished_at' => now(),
        ]);

        return $backup->fresh()->toEntity();
    }

    public function markFailed(string $id, string $error): Backup
    {
        $backup = BackupModel::findOrFail($id);

        $backup->update([
            'status' => Backup::STATUS_FAILED,
            'finished_at' => now(),
            // Truncated: a mysqldump failure can carry a very long stderr, and
            // the ledger is read in a list view.
            'error' => mb_substr($error, 0, 2000),
        ]);

        return $backup->fresh()->toEntity();
    }

    public function latestSuccessfulFinishedAtByTenant(string $kind): array
    {
        return BackupModel::query()
            ->selectRaw('COALESCE(tenant_id, \'\') as tenant_key, MAX(finished_at) as last_finished_at')
            ->where('kind', $kind)
            ->where('status', Backup::STATUS_OK)
            ->groupBy('tenant_key')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (string) $row->tenant_key => CarbonImmutable::parse($row->last_finished_at),
            ])
            ->all();
    }

    public function markPruned(string $id): Backup
    {
        $backup = BackupModel::findOrFail($id);

        // The row survives its file on purpose: "this backup existed and was
        // deleted by policy" is different from "this backup never happened",
        // and only the ledger can tell them apart afterwards.
        $backup->update([
            'status' => Backup::STATUS_PRUNED,
            'pruned_at' => now(),
        ]);

        return $backup->fresh()->toEntity();
    }
}
