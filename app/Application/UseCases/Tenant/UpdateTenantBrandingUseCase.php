<?php

namespace App\Application\UseCases\Tenant;

use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Entities\Tenant;
use App\Domain\Repositories\TenantRepositoryInterface;

class UpdateTenantBrandingUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private LogAuditUseCase $logAudit,
        private LogLandlordAuditUseCase $logLandlordAudit,
    ) {}

    public function execute(
        string $tenantId,
        string $adminId,
        ?string $themePrimaryColor = null,
        ?string $themeSecondaryColor = null,
        ?string $themeTertiaryColor = null,
        ?string $logoPath = null,
        ?array $iconPaths = null,
    ): Tenant {
        $tenant = $this->tenantRepository->update(
            id: $tenantId,
            themePrimaryColor: $themePrimaryColor,
            themeSecondaryColor: $themeSecondaryColor,
            themeTertiaryColor: $themeTertiaryColor,
            logoPath: $logoPath,
            iconPaths: $iconPaths,
        );

        $this->logLandlordAudit->execute(
            actorId: $adminId,
            action: 'tenant_updated_by_tenant_owner',
            model: 'Tenant',
            modelId: $tenantId,
            metadata: ['tenant_id' => $tenantId],
        );

        $this->logAudit->execute(
            userId: $adminId,
            userType: 'Admin',
            action: 'tenant_branding_updated',
            modelType: 'Tenant',
            modelId: $tenantId,
        );

        return $tenant;
    }
}
