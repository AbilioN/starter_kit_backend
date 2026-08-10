<?php

namespace App\Application\UseCases\Tenant;

use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Application\UseCases\Landlord\RecordMockPaymentUseCase;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Jobs\RetrySettingsSyncJob;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ChangeTenantSubscriptionPlanUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private SubscriptionPlanRepositoryInterface $subscriptionPlanRepository,
        private LogAuditUseCase $logAudit,
        private LogLandlordAuditUseCase $logLandlordAudit,
        private RecordMockPaymentUseCase $recordMockPayment,
        private SyncAgentProfilesForTenantUseCase $syncAgentProfiles,
    ) {}

    public function execute(string $tenantId, string $adminId, string $subscriptionPlanId): void
    {
        // 1. Write landlord.
        $this->tenantRepository->update(id: $tenantId, subscriptionPlanId: $subscriptionPlanId);

        $this->logLandlordAudit->execute(
            actorId: $adminId,
            action: 'tenant_updated_by_tenant_owner',
            model: 'Tenant',
            modelId: $tenantId,
            metadata: ['tenant_id' => $tenantId],
        );

        $this->recordMockPayment->execute($tenantId, $subscriptionPlanId, trigger: 'plan_change');

        // 2. Attempt to re-sync tenant settings.features.* - already on the
        // right (tenant) connection, no switch needed: this use case only
        // ever runs from a tenant-resolved request.
        try {
            $this->syncFeaturesFromPlan($subscriptionPlanId);
            $this->syncLimitsFromPlan($subscriptionPlanId);
            $this->syncAgentProfiles->execute($subscriptionPlanId);
        } catch (Throwable $e) {
            $this->logAudit->execute(
                userId: $adminId,
                userType: 'Admin',
                action: 'subscription_plan_change_sync_failed',
                modelType: 'Tenant',
                modelId: $tenantId,
                description: $e->getMessage(),
            );

            // Landlord write already succeeded - accept eventual
            // consistency and retry the settings sync out-of-band rather
            // than failing the whole request.
            RetrySettingsSyncJob::dispatch($tenantId, $subscriptionPlanId);

            return;
        }

        $this->logAudit->execute(
            userId: $adminId,
            userType: 'Admin',
            action: 'subscription_plan_changed',
            modelType: 'Tenant',
            modelId: $tenantId,
        );
    }

    public function syncFeaturesFromPlan(string $subscriptionPlanId): void
    {
        $plan = $this->subscriptionPlanRepository->findById($subscriptionPlanId);

        if (! $plan) {
            throw new \RuntimeException("Subscription plan {$subscriptionPlanId} not found.");
        }

        foreach ($plan->features as $key => $value) {
            Setting::updateOrCreate(
                ['key' => "features.{$key}"],
                [
                    'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                    'type' => is_bool($value) ? 'boolean' : 'string',
                    'group' => 'features',
                    'label' => Str::headline($key),
                ],
            );
        }
    }

    public function syncLimitsFromPlan(string $subscriptionPlanId): void
    {
        $plan = $this->subscriptionPlanRepository->findById($subscriptionPlanId);

        if (! $plan) {
            throw new \RuntimeException("Subscription plan {$subscriptionPlanId} not found.");
        }

        foreach ($plan->limits as $key => $value) {
            // null = unlimited. Unlike seedLimitsFromPlan() (fresh
            // provisioning, nothing to clean up), this runs when an
            // existing tenant CHANGES plans - a previous plan may have left
            // a numeric cap in place, so switching to unlimited must delete
            // it, not just skip writing a new one, or the stale cap would
            // keep being enforced.
            if ($value === null) {
                Setting::where('key', "limits.{$key}")->delete();
                $this->bustSettingCache("limits.{$key}");

                continue;
            }

            Setting::updateOrCreate(
                ['key' => "limits.{$key}"],
                [
                    'value' => (string) $value,
                    'type' => 'integer',
                    'group' => 'limits',
                    'label' => Str::headline($key),
                ],
            );
            $this->bustSettingCache("limits.{$key}");
        }
    }

    /**
     * Mirrors SettingRepository's private cache keys - this use case writes
     * to the Setting model directly (updateOrCreate/delete) rather than
     * through SettingRepositoryInterface, so Settings::get() would keep
     * serving a stale cached value (up to SettingRepository::CACHE_TTL)
     * without this. Most consequential for max_storage_mb: switching a
     * tenant to an unlimited plan must take effect immediately, not up to
     * an hour later.
     */
    private function bustSettingCache(string $key): void
    {
        Cache::forget('setting:'.$key);
        Cache::forget('settings:all');
        Cache::forget('settings:public');
    }
}
