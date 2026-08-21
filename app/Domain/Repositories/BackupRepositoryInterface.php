<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Backup;

interface BackupRepositoryInterface
{
    public function findById(string $id): ?Backup;

    /**
     * @return Backup[]
     */
    public function findForTenant(?string $tenantId, ?string $kind = null, int $limit = 50): array;

    /**
     * The staleness and capacity questions both start here.
     */
    public function findLatestSuccessful(?string $tenantId, string $kind): ?Backup;

    /**
     * Successful, not-yet-pruned backups, oldest first — the order pruning must
     * consume them in.
     *
     * @return Backup[]
     */
    public function findPrunable(?string $tenantId, string $kind): array;

    /**
     * Bytes currently stored for this tenant, excluding pruned rows.
     */
    public function totalStoredBytes(?string $tenantId): int;

    /**
     * Rows left `running` past a cutoff: crashed runs, not slow ones.
     *
     * @return Backup[]
     */
    public function findStuckRunning(\DateTimeInterface $olderThan): array;

    public function startRun(
        ?string $tenantId,
        string $kind,
        ?string $requestId = null,
    ): Backup;

    public function markSucceeded(
        string $id,
        ?string $providerId,
        string $diskName,
        string $destinationPath,
        int $sizeBytes,
        string $checksum,
        bool $isEncrypted,
    ): Backup;

    public function markFailed(string $id, string $error): Backup;

    public function markPruned(string $id): Backup;

    /**
     * Latest successful finish time per tenant, in one query.
     *
     * The staleness check runs behind a probe, so it must not become one query
     * per tenant — and it must never open a tenant *connection*: the whole
     * point is to answer while a tenant's own database is unreachable.
     *
     * @return array<string, \DateTimeInterface> keyed by tenant id ('' = landlord)
     */
    public function latestSuccessfulFinishedAtByTenant(string $kind): array;
}
