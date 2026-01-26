<?php

namespace App\Application\UseCases\Audit;

use App\Domain\Repositories\AuditLogRepositoryInterface;
use App\Application\DTOs\Audit\AuditLogDto;

class GetAuditLogsUseCase
{
    public function __construct(
        private AuditLogRepositoryInterface $auditRepository
    ) {}

    /**
     * Busca logs com filtros
     */
    public function execute(array $filters = [], int $perPage = 50): array
    {
        $result = $this->auditRepository->findWithFilters($filters, $perPage);

        return [
            'data' => array_map(
                fn($entity) => $entity->toDto()->toArray(),
                $result['data']
            ),
            'pagination' => $result['pagination'],
        ];
    }

    /**
     * Busca logs por modelo
     */
    public function getForModel(string $modelType, int $modelId): array
    {
        $logs = $this->auditRepository->findByModel($modelType, $modelId);

        return array_map(
            fn($entity) => $entity->toDto()->toArray(),
            $logs
        );
    }

    /**
     * Busca logs por usuário
     */
    public function getForUser(int $userId, string $userType, int $perPage = 50): array
    {
        $result = $this->auditRepository->findByUser($userId, $userType, $perPage);

        return [
            'data' => array_map(
                fn($entity) => $entity->toDto()->toArray(),
                $result['data']
            ),
            'pagination' => $result['pagination'],
        ];
    }

    /**
     * Busca log por ID
     */
    public function getById(int $id): ?array
    {
        $log = $this->auditRepository->findById($id);
        return $log ? $log->toDto()->toArray() : null;
    }

    /**
     * Busca logs por ação
     */
    public function getByAction(string $action, int $perPage = 50): array
    {
        $result = $this->auditRepository->findByAction($action, $perPage);

        return [
            'data' => array_map(
                fn($entity) => $entity->toDto()->toArray(),
                $result['data']
            ),
            'pagination' => $result['pagination'],
        ];
    }

    /**
     * Busca logs por tag
     */
    public function getByTag(string $tag, int $perPage = 50): array
    {
        $result = $this->auditRepository->findByTag($tag, $perPage);

        return [
            'data' => array_map(
                fn($entity) => $entity->toDto()->toArray(),
                $result['data']
            ),
            'pagination' => $result['pagination'],
        ];
    }

    /**
     * Busca logs por período
     */
    public function getByDateRange(?string $from = null, ?string $to = null, int $perPage = 50): array
    {
        $result = $this->auditRepository->findByDateRange($from, $to, $perPage);

        return [
            'data' => array_map(
                fn($entity) => $entity->toDto()->toArray(),
                $result['data']
            ),
            'pagination' => $result['pagination'],
        ];
    }
}

