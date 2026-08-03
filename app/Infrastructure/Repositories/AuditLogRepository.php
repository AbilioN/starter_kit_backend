<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\AuditLog;
use App\Domain\Repositories\AuditLogRepositoryInterface;
use App\Models\AuditLog as AuditLogModel;
use Illuminate\Support\Facades\DB;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function log(
        string $userId,
        string $userType,
        string $action,
        string $modelType,
        ?string $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $url = null,
        ?string $method = null,
        ?array $tags = null,
        ?array $metadata = null
    ): AuditLog {
        // Sanitizar valores sensíveis
        $oldValues = $this->sanitizeValues($oldValues);
        $newValues = $this->sanitizeValues($newValues);

        // Obter nome do usuário se disponível
        $userName = $this->getUserName($userId, $userType);

        $model = AuditLogModel::create([
            'user_id' => $userId,
            'user_type' => $userType,
            'user_name' => $userName,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'url' => $url,
            'method' => $method,
            'tags' => $tags,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        return $model->toEntity();
    }

    public function findWithFilters(array $filters = [], int $perPage = 50): array
    {
        $query = AuditLogModel::query();

        // Filtro por usuário
        if (isset($filters['user_id']) && isset($filters['user_type'])) {
            $query->forUser($filters['user_id'], $filters['user_type']);
        }

        // Filtro por modelo
        if (isset($filters['model_type'])) {
            $query->forModel($filters['model_type'], $filters['model_id'] ?? null);
        }

        // Filtro por ação
        if (isset($filters['action'])) {
            $query->forAction($filters['action']);
        }

        // Filtro por tags
        if (isset($filters['tags'])) {
            foreach ((array) $filters['tags'] as $tag) {
                $query->withTag($tag);
            }
        }

        // Filtro por data
        if (isset($filters['date_from']) || isset($filters['date_to'])) {
            $query->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        }

        // Ordenação
        $query->orderBy('created_at', 'desc');

        // Paginação
        $paginator = $query->paginate($perPage);

        return [
            'data' => array_map(fn($model) => $model->toEntity(), $paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function findByModel(string $modelType, string $modelId): array
    {
        return AuditLogModel::forModel($modelType, $modelId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($model) => $model->toEntity())
            ->toArray();
    }

    public function findByUser(string $userId, string $userType, int $perPage = 50): array
    {
        $paginator = AuditLogModel::forUser($userId, $userType)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'data' => array_map(fn($model) => $model->toEntity(), $paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function findById(string $id): ?AuditLog
    {
        $model = AuditLogModel::find($id);
        return $model ? $model->toEntity() : null;
    }

    public function findByAction(string $action, int $perPage = 50): array
    {
        $paginator = AuditLogModel::forAction($action)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'data' => array_map(fn($model) => $model->toEntity(), $paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function findByTag(string $tag, int $perPage = 50): array
    {
        $paginator = AuditLogModel::withTag($tag)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'data' => array_map(fn($model) => $model->toEntity(), $paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function findByDateRange(?string $from = null, ?string $to = null, int $perPage = 50): array
    {
        $paginator = AuditLogModel::dateRange($from, $to)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'data' => array_map(fn($model) => $model->toEntity(), $paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * Remove campos sensíveis dos valores
     */
    private function sanitizeValues(?array $values): ?array
    {
        if (!$values) {
            return null;
        }

        $sensitiveFields = ['password', 'token', 'secret', 'api_key', 'access_token', 'refresh_token'];

        foreach ($sensitiveFields as $field) {
            if (isset($values[$field])) {
                $values[$field] = '***REDACTED***';
            }
        }

        return $values;
    }

    /**
     * Obtém o nome do usuário
     */
    private function getUserName(string $userId, string $userType): ?string
    {
        try {
            if ($userType === 'Admin') {
                $user = \App\Models\Admin::find($userId);
                return $user?->name;
            }

            if ($userType === 'User') {
                $user = \App\Models\User::find($userId);
                return $user?->name;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}

