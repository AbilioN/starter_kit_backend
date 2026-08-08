<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Entities\InfrastructureProvider;
use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;

class CreateInfrastructureProviderUseCase
{
    public function __construct(
        private InfrastructureProviderRepositoryInterface $providerRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
    ) {}

    public function execute(
        string $actorId,
        string $type,
        string $name,
        array $config,
        bool $isActive = true,
    ): InfrastructureProvider {
        $provider = $this->providerRepository->create(
            type: $type,
            name: $name,
            config: $config,
            isActive: $isActive,
        );

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: 'infrastructure_provider_created',
            model: 'InfrastructureProvider',
            modelId: $provider->id,
            metadata: ['type' => $type],
        );

        return $provider;
    }
}
