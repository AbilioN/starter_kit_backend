<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Models\Admin;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Ends a support session by destroying the token behind it.
 *
 * Called from inside the impersonated session itself (the "Exit support
 * session" button in the admin panel), so the tenant connection is already
 * established — no switching here, unlike StartImpersonationUseCase.
 *
 * The session also ends on its own when the token expires. This exists so an
 * operator who is finished does not leave a usable key alive for the rest of
 * the window, and so the audit log records how long the access actually
 * lasted rather than how long it was allowed to.
 */
class StopImpersonationUseCase
{
    public function __construct(
        private LogLandlordAuditUseCase $logLandlordAudit,
        private LogAuditUseCase $logTenantAudit,
    ) {}

    public function execute(Admin $admin, PersonalAccessToken $token): void
    {
        $godAdminId = (string) $token->impersonated_by;

        $this->logTenantAudit->execute(
            userId: $godAdminId,
            userType: 'GodAdmin',
            action: 'impersonation_ended',
            modelType: Admin::class,
            modelId: (string) $admin->id,
            description: sprintf('Platform operator ended the support session as %s', $admin->email),
            tags: ['security', 'impersonation'],
            metadata: [
                'godadmin_id' => $godAdminId,
                'started_at' => $token->created_at?->toISOString(),
                'ended_at' => now()->toISOString(),
            ],
        );

        $this->logLandlordAudit->execute(
            actorId: $godAdminId,
            action: 'impersonation_ended',
            model: 'Admin',
            modelId: (string) $admin->id,
            metadata: ['started_at' => $token->created_at?->toISOString()],
        );

        // Last, so a failure while writing the audit entries cannot leave the
        // session ended but unrecorded.
        $token->delete();
    }
}
