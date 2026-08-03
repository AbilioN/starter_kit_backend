<?php

namespace App\Application\UseCases\Landlord;

use App\Domain\Repositories\LandlordAuditLogRepositoryInterface;

class LogLandlordAuditUseCase
{
    public function __construct(
        private LandlordAuditLogRepositoryInterface $landlordAuditLogRepository
    ) {}

    public function execute(
        string $actorId,
        string $action,
        string $model,
        ?string $modelId = null,
        ?array $metadata = null
    ): void {
        $this->landlordAuditLogRepository->log(
            actorType: 'godadmin',
            actorId: $actorId,
            action: $action,
            model: $model,
            modelId: $modelId,
            metadata: $metadata,
        );
    }
}
