<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Entities\Tenant;
use App\Domain\Repositories\TenantRepositoryInterface;
use InvalidArgumentException;

class SuspendTenantUseCase
{
    private const ALLOWED_STATUSES = ['active', 'suspended'];

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
    ) {}

    public function execute(string $actorId, string $tenantId, string $status): Tenant
    {
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid tenant status: {$status}");
        }

        $tenant = $this->tenantRepository->update(id: $tenantId, status: $status);

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: $status === 'suspended' ? 'tenant_suspended' : 'tenant_reactivated',
            model: 'Tenant',
            modelId: $tenant->id,
        );

        return $tenant;
    }
}
