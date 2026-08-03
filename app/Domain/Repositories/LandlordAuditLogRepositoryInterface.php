<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\LandlordAuditLog;

interface LandlordAuditLogRepositoryInterface
{
    public function log(
        string $actorType,
        string $actorId,
        string $action,
        string $model,
        ?string $modelId = null,
        ?array $metadata = null
    ): LandlordAuditLog;

    public function findWithFilters(array $filters = [], int $perPage = 50): array;
}
