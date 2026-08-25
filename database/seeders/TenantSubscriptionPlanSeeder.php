<?php

namespace Database\Seeders;

use App\Application\UseCases\Tenant\ChangeTenantSubscriptionPlanUseCase;
use App\Application\UseCases\Tenant\SyncAgentProfilesForTenantUseCase;
use App\Domain\Services\TenantConnectionSwitcherInterface;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Landlord seeder, not tenant. Invoke directly, after SubscriptionPlanSeeder:
 * php artisan db:seed --class=TenantSubscriptionPlanSeeder
 *
 * Puts the demo tenants (tenant-a, tenant-b - see docs/04-local-dev-tenants.md)
 * on a plan. `tenant:provision` accepts --plan but starter.sh never passes one,
 * so both demo tenants come out of provisioning with subscription_plan_id null:
 * every feature flag absent and every limit unlimited, which is the one
 * configuration the product never actually ships. tenant-a on pro and tenant-b
 * one tenant per tier is deliberate: every plan-dependent behaviour is then
 * visible side by side without editing a plan mid-demo - tenant-b (free) has
 * ai_agent and backup off and tight caps, tenant-a (pro) has both on with real
 * caps and two agents, tenant-c (enterprise) is uncapped and is the only one
 * that sees the third agent.
 *
 * Assigning a plan is TWO writes, not one. The landlord column is what the
 * scheduler reads (backups, deliberately - see docs/09-backup-and-restore.md),
 * while EnforcePlanLimitUseCase and the feature flags read a MIRROR of
 * `limits.*`/`features.*` inside each tenant's own settings table. Setting only
 * the column leaves a tenant that has a plan on paper and none in effect, so
 * this reuses ChangeTenantSubscriptionPlanUseCase's public sync methods (the
 * same ones RetrySettingsSyncJob calls) inside the tenant connection rather
 * than reimplementing the mirror.
 *
 * Skips a subdomain that hasn't been provisioned yet, and a plan slug that
 * hasn't been seeded, rather than failing - safe to run on any environment.
 * Re-running is idempotent: the sync methods updateOrCreate their settings.
 */
class TenantSubscriptionPlanSeeder extends Seeder
{
    private const ASSIGNMENTS = [
        'tenant-a' => 'pro',
        'tenant-b' => 'free',
        'tenant-c' => 'enterprise',
    ];

    public function __construct(
        private readonly TenantConnectionSwitcherInterface $tenantConnection,
        private readonly ChangeTenantSubscriptionPlanUseCase $changeTenantSubscriptionPlan,
        private readonly SyncAgentProfilesForTenantUseCase $syncAgentProfiles,
    ) {}

    public function run(): void
    {
        foreach (self::ASSIGNMENTS as $subdomain => $slug) {
            $tenant = Tenant::where('subdomain', $subdomain)->first();

            if (! $tenant) {
                $this->command?->warn("Skipping '{$subdomain}': tenant not provisioned yet.");

                continue;
            }

            $plan = SubscriptionPlan::where('slug', $slug)->first();

            if (! $plan) {
                $this->command?->warn("Skipping '{$subdomain}': plan '{$slug}' not seeded yet.");

                continue;
            }

            $tenant->update(['subscription_plan_id' => $plan->id]);

            $this->tenantConnection->run($tenant->database_name, function () use ($plan) {
                $this->changeTenantSubscriptionPlan->syncFeaturesFromPlan($plan->id);
                $this->changeTenantSubscriptionPlan->syncLimitsFromPlan($plan->id);
                $this->syncAgentProfiles->execute($plan->id);
            });

            $this->command?->info("Assigned '{$slug}' to '{$subdomain}' (landlord + settings mirror).");
        }
    }
}
