<?php

namespace Tests\Feature\Backup;

use App\Application\UseCases\Backup\ResolveBackupPolicyUseCase;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use Tests\TenantTestCase;

/**
 * The plan is the source of truth for period and capacity — and, just as
 * importantly, absence of a key must never read as "backups off".
 */
class BackupPolicyTest extends TenantTestCase
{
    private function makeTenant(array $features = [], array $limits = []): \App\Domain\Entities\Tenant
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Test Plan',
            slug: 'test-plan-'.uniqid(),
            priceCents: 1000,
            features: $features,
            limits: $limits,
        );

        return app(TenantRepositoryInterface::class)->create(
            name: 'Acme',
            subdomain: 'acme-'.uniqid(),
            databaseName: 'tenant_acme_'.uniqid(),
            subscriptionPlanId: $plan->id,
            createdVia: 'godadmin',
        );
    }

    public function test_landlord_is_always_backed_up_and_never_capped(): void
    {
        $policy = app(ResolveBackupPolicyUseCase::class)->execute(null);

        $this->assertTrue($policy['enabled']);
        $this->assertNull($policy['max_total_mb']);
        $this->assertSame(24, $policy['frequency_hours']);
    }

    public function test_plan_limits_drive_period_and_capacity(): void
    {
        $tenant = $this->makeTenant(limits: [
            'backup_frequency_hours' => 168,
            'backup_retention_days' => 90,
            'backup_max_total_mb' => 2048,
        ]);

        $policy = app(ResolveBackupPolicyUseCase::class)->execute($tenant);

        $this->assertSame(168, $policy['frequency_hours']);
        $this->assertSame(90, $policy['retention_days']);
        $this->assertSame(2048, $policy['max_total_mb']);
    }

    /**
     * The regression that matters most here: a plan written before backups
     * existed has no `features.backup` key, and must keep being backed up.
     * Reading absence as false would silently stop backing up every existing
     * customer the day this shipped.
     */
    public function test_a_plan_without_the_backup_feature_key_is_still_backed_up(): void
    {
        $tenant = $this->makeTenant(features: ['chat' => true]);

        $this->assertTrue(app(ResolveBackupPolicyUseCase::class)->execute($tenant)['enabled']);
    }

    public function test_backups_can_be_switched_off_explicitly(): void
    {
        $byFeature = $this->makeTenant(features: ['backup' => false]);
        $byFrequency = $this->makeTenant(limits: ['backup_frequency_hours' => null]);

        $useCase = app(ResolveBackupPolicyUseCase::class);

        $this->assertFalse($useCase->execute($byFeature)['enabled']);
        $this->assertFalse($useCase->execute($byFrequency)['enabled']);
    }
}
