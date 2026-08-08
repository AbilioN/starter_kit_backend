<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Entities\InfrastructureProvider;
use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;

class UpdateInfrastructureProviderUseCase
{
    public function __construct(
        private InfrastructureProviderRepositoryInterface $providerRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
    ) {}

    public function execute(
        string $actorId,
        string $providerId,
        ?string $name = null,
        ?array $config = null,
        ?bool $isActive = null,
    ): InfrastructureProvider {
        $provider = $this->providerRepository->update(
            id: $providerId,
            name: $name,
            config: $config,
            isActive: $isActive,
        );

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: 'infrastructure_provider_updated',
            model: 'InfrastructureProvider',
            modelId: $provider->id,
        );

        return $provider;
    }
}
