<?php

namespace Tests\Feature\Tenant;

use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Models\Admin;
use App\Models\Setting;
use Tests\TenantTestCase;

class SubscriptionPlanChangeTest extends TenantTestCase
{
    public function test_tenant_owner_can_change_subscription_plan_and_settings_resync(): void
    {
        $tenant = $this->actingAsTenant();

        $owner = Admin::factory()->create(['is_tenant_owner' => true, 'is_active' => true]);
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro',
            slug: 'pro',
            priceCents: 9900,
            features: ['chat' => true, 'file_upload' => true],
            limits: ['max_admins' => 10],
        );

        $token = $owner->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/tenant/subscription-plan', ['subscription_plan_id' => $plan->id])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $updatedTenant = app(TenantRepositoryInterface::class)->findById($tenant->id);
        $this->assertSame($plan->id, $updatedTenant->subscriptionPlanId);

        $chatSetting = Setting::where('key', 'features.chat')->first();
        $this->assertNotNull($chatSetting);
        $this->assertSame('1', $chatSetting->value);
    }

    public function test_non_owner_admin_cannot_change_subscription_plan(): void
    {
        $this->actingAsTenant();

        $admin = Admin::factory()->create(['is_tenant_owner' => false, 'is_active' => true]);
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro',
            slug: 'pro',
            priceCents: 9900,
            features: [],
            limits: [],
        );
        $token = $admin->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/tenant/subscription-plan', ['subscription_plan_id' => $plan->id])
            ->assertStatus(403);
    }
}
