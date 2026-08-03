<?php

namespace App\Domain\Entities;

use DateTime;

class LandlordAuditLog
{
    public function __construct(
        public readonly string $id,
        public readonly string $actorType,
        public readonly string $actorId,
        public readonly string $action,
        public readonly string $model,
        public readonly ?string $modelId,
        public readonly ?array $metadata,
        public readonly ?DateTime $createdAt = null
    ) {}
}
