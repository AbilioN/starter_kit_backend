<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Entities\Tenant;
use App\Domain\Repositories\TenantRepositoryInterface;

class UpdateTenantBrandingUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
    ) {}

    public function execute(
        string $actorId,
        string $tenantId,
        ?string $themePrimaryColor = null,
        ?string $themeSecondaryColor = null,
        ?string $logoPath = null,
    ): Tenant {
        $tenant = $this->tenantRepository->update(
            id: $tenantId,
            themePrimaryColor: $themePrimaryColor,
            themeSecondaryColor: $themeSecondaryColor,
            logoPath: $logoPath,
        );

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: 'tenant_branding_updated',
            model: 'Tenant',
            modelId: $tenantId,
        );

        return $tenant;
    }
}
