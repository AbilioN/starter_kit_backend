<?php

namespace App\Domain\Entities;

use DateTime;

/**
 * One backup attempt.
 *
 * An *attempt*, not a stored file: the row is written before the dump starts,
 * so a crashed run is visible instead of leaving nothing behind at all.
 */
class Backup
{
    public const KIND_DATABASE = 'database';

    public const KIND_FILES = 'files';

    public const STATUS_RUNNING = 'running';

    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PRUNED = 'pruned';

    public function __construct(
        public readonly string $id,
        public readonly ?string $tenantId, // null = the landlord's own backup
        public readonly string $kind,
        public readonly string $status,
        public readonly ?string $providerId = null,
        public readonly ?string $diskName = null,
        public readonly ?string $destinationPath = null,
        public readonly ?int $sizeBytes = null,
        public readonly ?string $checksum = null,
        public readonly bool $isEncrypted = false,
        public readonly ?DateTime $startedAt = null,
        public readonly ?DateTime $finishedAt = null,
        public readonly ?DateTime $prunedAt = null,
        public readonly ?string $error = null,
        public readonly ?string $requestId = null,
    ) {}

    public function isRestorable(): bool
    {
        return $this->status === self::STATUS_OK && $this->destinationPath !== null;
    }
}
