<?php

namespace Tests\Feature\Tenant;

use App\Application\UseCases\Tenant\ChangeTenantSubscriptionPlanUseCase;
use App\Helpers\Settings;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use Tests\TenantTestCase;

/**
 * A plan change must be visible on the very next read.
 *
 * Two bugs fixed on 2026-09-04, both silent:
 *
 * 1. `bustSettingCache()` forgot `setting:<key>` while SettingRepository had
 *    written `<database name>:setting:<key>` — the tenant prefix that exists
 *    because Redis is shared across tenants. The forget therefore matched
 *    nothing, ever, and the comment above it described behaviour the code did
 *    not have.
 * 2. `syncFeaturesFromPlan()` never busted the cache at all — only
 *    `syncLimitsFromPlan()` did. So enabling a feature by moving a tenant onto
 *    a better plan did nothing for up to an hour.
 *
 * Both now go through App\Application\Services\TenantCacheKey, which is the
 * point: two files computing the same key independently is how invalidation
 * stops working without anything failing loudly.
 */
class PlanSettingCacheBustTest extends TenantTestCase
{
    private ChangeTenantSubscriptionPlanUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('plancache');

        $this->useCase = app(ChangeTenantSubscriptionPlanUseCase::class);
    }

    private function plan(array $features, array $limits): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan-'.uniqid(),
            'price_cents' => 1000,
            'features' => $features,
            'limits' => $limits,
            'is_active' => true,
        ]);
    }

    public function test_enabling_a_feature_by_plan_change_is_visible_immediately(): void
    {
        Setting::create([
            'key' => 'features.custom_fields_appointments',
            'value' => 'false',
            'type' => 'boolean',
            'group' => 'features',
            'label' => 'Custom Fields Appointments',
        ]);

        // Warm the cache the way the product does — through the helper every
        // feature gate reads from.
        $this->assertFalse(Settings::isEnabled('features.custom_fields_appointments'));

        $plan = $this->plan(['custom_fields_appointments' => true], []);
        $this->useCase->syncFeaturesFromPlan($plan->id);

        // Before the fix this still read false: syncFeaturesFromPlan busted
        // nothing, so the warm cache above survived the plan change.
        $this->assertTrue(Settings::isEnabled('features.custom_fields_appointments'));
    }

    public function test_raising_a_limit_by_plan_change_is_visible_immediately(): void
    {
        Setting::create([
            'key' => 'limits.max_custom_fields',
            'value' => '5',
            'type' => 'integer',
            'group' => 'limits',
            'label' => 'Max Custom Fields',
        ]);

        $this->assertSame(5, Settings::get('limits.max_custom_fields'));

        $plan = $this->plan([], ['max_custom_fields' => 40]);
        $this->useCase->syncLimitsFromPlan($plan->id);

        // syncLimitsFromPlan DID call bustSettingCache before the fix — but
        // with an unprefixed key that never matched what the repository wrote,
        // so this read 5 too.
        $this->assertSame(40, Settings::get('limits.max_custom_fields'));
    }

    public function test_switching_to_an_unlimited_plan_removes_the_cap_immediately(): void
    {
        Setting::create([
            'key' => 'limits.max_custom_fields',
            'value' => '5',
            'type' => 'integer',
            'group' => 'limits',
            'label' => 'Max Custom Fields',
        ]);

        $this->assertSame(5, Settings::get('limits.max_custom_fields'));

        // null means unlimited, and EnforcePlanLimitUseCase reads a missing
        // setting as no cap — so the row must actually be deleted AND the
        // cached copy of it forgotten.
        $plan = $this->plan([], ['max_custom_fields' => null]);
        $this->useCase->syncLimitsFromPlan($plan->id);

        $this->assertNull(Settings::get('limits.max_custom_fields'));
    }
}
