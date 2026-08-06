<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Application\UseCases\Landlord\NotifyTenantOwnerUseCase;
use App\Domain\Entities\Tenant;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Notifications\TenantReactivatedNotification;
use App\Notifications\TenantSuspendedNotification;
use InvalidArgumentException;
use Throwable;

class SuspendTenantUseCase
{
    private const ALLOWED_STATUSES = ['active', 'suspended'];

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
        private NotifyTenantOwnerUseCase $notifyTenantOwner,
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

        // The status change itself must never be blocked by a notification
        // failure (mail server down, etc.) — log it and move on.
        try {
            $this->notifyTenantOwner->execute(
                $tenant,
                $status === 'suspended' ? new TenantSuspendedNotification() : new TenantReactivatedNotification(),
            );
        } catch (Throwable $e) {
            $this->logLandlordAudit->execute(
                actorId: $actorId,
                action: 'tenant_owner_notification_failed',
                model: 'Tenant',
                modelId: $tenant->id,
                metadata: ['status' => $status, 'error' => $e->getMessage()],
            );
        }

        return $tenant;
    }
}
