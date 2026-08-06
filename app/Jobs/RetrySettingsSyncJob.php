<?php

namespace App\Jobs;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Application\UseCases\Tenant\ChangeTenantSubscriptionPlanUseCase;
use App\Jobs\Middleware\EstablishTenantConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Retries syncing settings.features.* and settings.limits.* from a
 * subscription plan after the landlord write already succeeded but the
 * tenant-side sync failed - the "eventual consistency" half of
 * ChangeTenantSubscriptionPlanUseCase.
 */
class RetrySettingsSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $backoff = [60, 300, 900, 3600];

    public function __construct(
        private string $tenantId,
        private string $subscriptionPlanId,
    ) {}

    public function middleware(): array
    {
        return [new EstablishTenantConnection($this->tenantId)];
    }

    public function handle(ChangeTenantSubscriptionPlanUseCase $changeTenantSubscriptionPlan): void
    {
        $changeTenantSubscriptionPlan->syncFeaturesFromPlan($this->subscriptionPlanId);
        $changeTenantSubscriptionPlan->syncLimitsFromPlan($this->subscriptionPlanId);
    }

    /**
     * All retries exhausted - HasAuditLog's automatic trait fails silently
     * on non-Throwable-safe exceptions, so this is the one place eventual-
     * consistency failures need to surface somewhere a human (GodAdmin) can
     * see them.
     */
    public function failed(Throwable $exception): void
    {
        app(LogLandlordAuditUseCase::class)->execute(
            actorId: '00000000-0000-0000-0000-000000000000',
            action: 'subscription_plan_sync_permanently_failed',
            model: 'Tenant',
            modelId: $this->tenantId,
            metadata: [
                'tenant_id' => $this->tenantId,
                'subscription_plan_id' => $this->subscriptionPlanId,
                'error' => $exception->getMessage(),
            ],
        );
    }
}
