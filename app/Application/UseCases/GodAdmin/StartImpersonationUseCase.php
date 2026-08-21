<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Domain\Services\TenantConnectionSwitcherInterface;
use App\Models\Admin;
use App\Models\GodAdmin;
use App\Notifications\TenantImpersonatedNotification;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issues a short-lived Sanctum token for a tenant admin, on behalf of a
 * GodAdmin, so support can see exactly what the customer sees without ever
 * knowing their password — which for a self-service signup nobody at the
 * platform ever had in the first place.
 *
 * Three properties this deliberately guarantees:
 *
 *  - **Read-only unless asked otherwise.** The default session cannot write.
 *    Reproducing a bug rarely needs to change the customer's data, and a
 *    support operator who alters it by accident is a far worse incident than a
 *    bug that stays unreproduced. Write access is a separate, separately
 *    audited request.
 *  - **Attributable.** Every audited write during the session records the
 *    GodAdmin behind it (see HasAuditLog), so the trail can never say the
 *    tenant's own admin did something an operator did. That is the difference
 *    between an audit log and a plausible-looking fiction.
 *  - **Visible to the customer.** The tenant's own immutable audit log gets the
 *    entry, and the tenant owner is notified. "Clear to both parties" is the
 *    requirement; a record only the platform can read does not meet it.
 */
class StartImpersonationUseCase
{
    public const MODE_READ = 'read';

    public const MODE_WRITE = 'write';

    /**
     * Long enough to work a support ticket, short enough that a forgotten
     * browser tab is not a standing key to a customer's account.
     */
    public const SESSION_MINUTES = 30;

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
        private LogAuditUseCase $logTenantAudit,
        private TenantConnectionSwitcherInterface $tenantConnection,
    ) {}

    /**
     * @return array{token: string, expires_at: string, admin_id: string, admin_name: string, subdomain: string, mode: string}
     */
    public function execute(
        string $godAdminId,
        string $tenantId,
        string $adminId,
        string $mode = self::MODE_READ,
        ?string $reason = null,
    ): array {
        if (! in_array($mode, [self::MODE_READ, self::MODE_WRITE], true)) {
            throw new DomainException("Invalid impersonation mode: {$mode}");
        }

        $tenant = $this->tenantRepository->findById($tenantId);

        if (! $tenant) {
            throw new DomainException('Tenant not found.');
        }

        if ($tenant->status !== 'active') {
            // A suspended tenant's API rejects every request anyway
            // (IdentifyTenant), so a token minted here would be dead on
            // arrival — better to say so than to hand over a useless session.
            throw new DomainException('Cannot impersonate inside a tenant that is not active.');
        }

        $godAdmin = GodAdmin::find($godAdminId);
        $expiresAt = Carbon::now()->addMinutes(self::SESSION_MINUTES);

        $session = $this->tenantConnection->run($tenant->databaseName, function () use ($adminId, $godAdminId, $mode, $expiresAt, $reason, $godAdmin, $tenant) {
            $admin = Admin::find($adminId);

            if (! $admin) {
                throw new DomainException('Admin not found in this tenant.');
            }

            if (! $admin->is_active) {
                throw new DomainException('Cannot impersonate an inactive admin.');
            }

            // Abilities carry read-vs-write; the impersonated_by column carries
            // "this is a support session at all". They cannot be collapsed into
            // one: ordinary admin tokens are created with ['*'], so any ability
            // check against them answers true.
            $abilities = $mode === self::MODE_WRITE
                ? ['impersonation:read', 'impersonation:write']
                : ['impersonation:read'];

            $token = $admin->createToken('godadmin-impersonation', $abilities, $expiresAt);
            $token->accessToken->forceFill(['impersonated_by' => $godAdminId])->save();

            $metadata = [
                'godadmin_id' => $godAdminId,
                'godadmin_email' => $godAdmin?->email,
                'mode' => $mode,
                'reason' => $reason,
                'expires_at' => $expiresAt->toISOString(),
            ];

            // Written into the TENANT's own immutable audit log, as the
            // GodAdmin rather than as the admin being impersonated — the
            // customer must be able to see this in their own /audit page.
            $this->logTenantAudit->execute(
                userId: $godAdminId,
                userType: 'GodAdmin',
                action: 'impersonation_started',
                modelType: Admin::class,
                modelId: $adminId,
                description: sprintf(
                    'Platform operator %s started a %s support session as %s',
                    $godAdmin?->email ?? $godAdminId,
                    $mode === self::MODE_WRITE ? 'read-write' : 'read-only',
                    $admin->email,
                ),
                tags: ['security', 'impersonation'],
                metadata: $metadata,
            );

            $this->notifyOwner($admin, $godAdmin?->email ?? $godAdminId, $mode, $expiresAt, $reason);

            return [
                'token' => $token->plainTextToken,
                'expires_at' => $expiresAt->toISOString(),
                'admin_id' => (string) $admin->id,
                'admin_name' => (string) $admin->name,
                'subdomain' => $tenant->subdomain,
                'mode' => $mode,
                'metadata' => $metadata,
            ];
        });

        $this->logLandlordAudit->execute(
            actorId: $godAdminId,
            action: 'impersonation_started',
            model: 'Tenant',
            modelId: $tenantId,
            metadata: $session['metadata'] + ['admin_id' => $adminId, 'subdomain' => $tenant->subdomain],
        );

        unset($session['metadata']);

        return $session;
    }

    /**
     * The tenant owner is told an operator is in their account. Deliberately
     * best-effort: a mail or notification failure must not abort a support
     * session that is already fully recorded in two audit logs.
     */
    private function notifyOwner(Admin $impersonated, string $operator, string $mode, Carbon $expiresAt, ?string $reason): void
    {
        try {
            $owner = Admin::where('is_tenant_owner', true)->first() ?? $impersonated;

            $owner->notify(new TenantImpersonatedNotification(
                operator: $operator,
                impersonatedAdminName: (string) $impersonated->name,
                mode: $mode,
                expiresAt: $expiresAt,
                reason: $reason,
            ));
        } catch (Throwable $e) {
            // Swallowed on purpose — see the docblock. Logged, though, and not
            // silently: a notification that fails without a trace is how this
            // project discovered that `database`-channel notifications had
            // never worked at all (see the 2026_08_21 notifiable_id migration).
            Log::warning('Impersonation notice to tenant owner failed', [
                'error' => $e->getMessage(),
                'admin_id' => (string) $impersonated->id,
            ]);
        }
    }
}
