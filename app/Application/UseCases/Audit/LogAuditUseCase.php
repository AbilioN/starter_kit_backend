<?php

namespace App\Application\UseCases\Audit;

use App\Domain\Repositories\AuditLogRepositoryInterface;

class LogAuditUseCase
{
    public function __construct(
        private AuditLogRepositoryInterface $auditRepository
    ) {}

    public function execute(
        int $userId,
        string $userType,
        string $action,
        string $modelType,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?array $tags = null,
        ?array $metadata = null
    ): void {
        $this->auditRepository->log(
            userId: $userId,
            userType: $userType,
            action: $action,
            modelType: $modelType,
            modelId: $modelId,
            oldValues: $oldValues,
            newValues: $newValues,
            description: $description,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            url: request()->fullUrl(),
            method: request()->method(),
            tags: $tags,
            metadata: $metadata
        );
    }
}

