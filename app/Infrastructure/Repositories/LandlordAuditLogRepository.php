<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\LandlordAuditLog;
use App\Domain\Repositories\LandlordAuditLogRepositoryInterface;
use App\Models\LandlordAuditLog as LandlordAuditLogModel;

class LandlordAuditLogRepository implements LandlordAuditLogRepositoryInterface
{
    public function log(
        string $actorType,
        string $actorId,
        string $action,
        string $model,
        ?string $modelId = null,
        ?array $metadata = null
    ): LandlordAuditLog {
        $log = LandlordAuditLogModel::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'model' => $model,
            'model_id' => $modelId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        return $log->toEntity();
    }

    public function findWithFilters(array $filters = [], int $perPage = 50): array
    {
        $query = LandlordAuditLogModel::query();

        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['model'])) {
            $query->where('model', $filters['model']);
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return [
            'data' => array_map(fn (LandlordAuditLogModel $log) => $log->toEntity(), $paginator->items()),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
