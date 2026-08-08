<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Entities\Tenant;
use App\Domain\Repositories\TenantRepositoryInterface;

/**
 * Sets/clears a tenant's own broadcasting/storage provider override — a
 * landlord-only write (unlike ChangeTenantSubscriptionPlanAsGodAdminUseCase,
 * this never touches the tenant's own database, so no connection-pointing
 * dance is needed: no Setting rows to sync, just tenants.broadcasting_provider_id
 * / storage_provider_id).
 */
class UpdateTenantInfrastructureUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
    ) {}

    public function execute(
        string $actorId,
        string $tenantId,
        ?string $broadcastingProviderId = null,
        ?string $storageProviderId = null,
        ?string $aiProviderId = null,
        bool $clearBroadcastingProvider = false,
        bool $clearStorageProvider = false,
        bool $clearAiProvider = false,
    ): Tenant {
        $tenant = $this->tenantRepository->update(
            id: $tenantId,
            broadcastingProviderId: $broadcastingProviderId,
            storageProviderId: $storageProviderId,
            aiProviderId: $aiProviderId,
            clearBroadcastingProvider: $clearBroadcastingProvider,
            clearStorageProvider: $clearStorageProvider,
            clearAiProvider: $clearAiProvider,
        );

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: 'tenant_infrastructure_updated',
            model: 'Tenant',
            modelId: $tenantId,
        );

        return $tenant;
    }
}
