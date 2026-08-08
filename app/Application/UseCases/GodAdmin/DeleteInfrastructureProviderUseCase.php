<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;

class DeleteInfrastructureProviderUseCase
{
    public function __construct(
        private InfrastructureProviderRepositoryInterface $providerRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
    ) {}

    public function execute(string $actorId, string $providerId): void
    {
        // FK columns on subscription_plans/tenants use nullOnDelete() — any
        // plan/tenant pointing at this provider just falls back to "nothing
        // configured" (global .env defaults), never a broken reference.
        $this->providerRepository->delete($providerId);

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: 'infrastructure_provider_deleted',
            model: 'InfrastructureProvider',
            modelId: $providerId,
        );
    }
}
