<?php

namespace App\Application\UseCases\Tenant;

use App\Domain\Exceptions\PlanLimitExceededException;
use App\Helpers\Settings;
use Closure;

class EnforcePlanLimitUseCase
{
    /**
     * Checks a discrete (count-based) plan limit — max admins, max users —
     * seeded into tenant settings as `limits.{$limitKey}` at provisioning
     * and re-synced on plan change (see ProvisionTenantUseCase::seedLimitsFromPlan()
     * / ChangeTenantSubscriptionPlanUseCase::syncLimitsFromPlan()).
     *
     * No limit set (null) means unlimited — either no plan is assigned, or
     * this tenant predates limit seeding. $currentCountResolver is lazy
     * (only invoked once a limit is confirmed to exist): skips the count
     * query entirely on the common unlimited path, and keeps callers like
     * RegisterUseCase unit-testable with this use case mocked out, without
     * a real Eloquent count ever running.
     */
    public function execute(string $limitKey, Closure $currentCountResolver): void
    {
        $limit = Settings::get("limits.{$limitKey}");

        if ($limit === null) {
            return;
        }

        if ($currentCountResolver() >= (int) $limit) {
            throw new PlanLimitExceededException(
                "This tenant's plan allows a maximum of {$limit} for '{$limitKey}'. Upgrade the plan to add more."
            );
        }
    }
}
