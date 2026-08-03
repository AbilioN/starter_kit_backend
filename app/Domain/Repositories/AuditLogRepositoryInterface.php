<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\AuditLog;

interface AuditLogRepositoryInterface
{
    /**
     * Registra um log de auditoria
     */
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
    ): AuditLog;

    /**
     * Busca logs com filtros
     */
    public function findWithFilters(
        array $filters = [],
        int $perPage = 50
    ): array;

    /**
     * Busca logs por modelo
     */
    public function findByModel(string $modelType, string $modelId): array;

    /**
     * Busca logs por usuário
     */
    public function findByUser(string $userId, string $userType, int $perPage = 50): array;

    /**
     * Busca log por ID
     */
    public function findById(string $id): ?AuditLog;

    /**
     * Busca logs por ação
     */
    public function findByAction(string $action, int $perPage = 50): array;

    /**
     * Busca logs por tags
     */
    public function findByTag(string $tag, int $perPage = 50): array;

    /**
     * Busca logs por período
     */
    public function findByDateRange(?string $from = null, ?string $to = null, int $perPage = 50): array;
}

