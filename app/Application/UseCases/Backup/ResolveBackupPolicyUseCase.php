<?php

namespace App\Application\UseCases\Backup;

use App\Domain\Entities\Tenant;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;

/**
 * How often a tenant is backed up, for how long copies are kept, and how much
 * space they may occupy — all three come from its plan.
 *
 * **Read from the landlord's `subscription_plans.limits`, never from the
 * tenant's own `settings` table.** ProvisionTenantUseCase and
 * ChangeTenantSubscriptionPlanUseCase mirror every `limits.*` key into the
 * tenant database, and EnforcePlanLimitUseCase reads it from there — that path
 * is right for in-request enforcement and wrong here, twice over: the mirror is
 * a snapshot that RetrySettingsSyncJob may still be retrying, and reading it
 * means connecting to the tenant's database, which is exactly the thing that
 * may be broken on the day its backup matters.
 */
class ResolveBackupPolicyUseCase
{
    public function __construct(
        private SubscriptionPlanRepositoryInterface $planRepository,
    ) {}

    /**
     * @return array{enabled: bool, frequency_hours: ?int, retention_days: int, max_total_mb: ?int}
     */
    public function execute(?Tenant $tenant): array
    {
        $defaults = config('backup.defaults');

        // The landlord has no plan, and is backed up regardless of any: without
        // it every surviving tenant dump is an anonymous file, with no map from
        // subdomain to database_name to restore it by.
        if ($tenant === null) {
            return [
                'enabled' => true,
                'frequency_hours' => (int) $defaults['frequency_hours'],
                'retention_days' => (int) $defaults['retention_days'],
                'max_total_mb' => null,
            ];
        }

        $plan = $tenant->subscriptionPlanId
            ? $this->planRepository->findById($tenant->subscriptionPlanId)
            : null;

        $limits = $plan?->limits ?? [];
        $features = $plan?->features ?? [];

        // A free tier with no backups is a real product decision, so an explicit
        // `features.backup = false` is honoured. Absence is not: a plan that
        // simply predates this feature must keep being backed up.
        $enabled = ! array_key_exists('backup', $features) || (bool) $features['backup'];

        $frequency = array_key_exists('backup_frequency_hours', $limits)
            ? $limits['backup_frequency_hours']
            : $defaults['frequency_hours'];

        // Explicit null frequency means "never" — the only way to switch a
        // tenant's backups off through limits rather than features.
        if ($frequency === null) {
            $enabled = false;
        }

        return [
            'enabled' => $enabled,
            'frequency_hours' => $frequency === null ? null : (int) $frequency,
            'retention_days' => (int) ($limits['backup_retention_days'] ?? $defaults['retention_days']),
            'max_total_mb' => array_key_exists('backup_max_total_mb', $limits)
                ? ($limits['backup_max_total_mb'] === null ? null : (int) $limits['backup_max_total_mb'])
                : $defaults['max_total_mb'],
        ];
    }
}
