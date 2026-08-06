<?php

namespace Tests\Feature\Landlord;

use App\Application\UseCases\Tenant\ProvisionTenantUseCase;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Models\Admin;
use App\Models\MockPayment;
use Tests\TenantTestCase;

class MockPaymentLedgerTest extends TenantTestCase
{
    public function test_provisioning_a_tenant_with_a_plan_records_a_signup_mock_payment(): void
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro', slug: 'pro', priceCents: 9900, features: [], limits: [], isActive: true, isPublic: true
        );

        $tenant = app(ProvisionTenantUseCase::class)->execute(
            name: 'Ledger Co',
            subdomain: 'ledgerco',
            subscriptionPlanId: $plan->id,
            createdVia: 'godadmin',
            adminEmail: 'owner@ledgerco.test',
            adminPassword: 'super-secret',
        );

        $payment = MockPayment::on('landlord')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(9900, $payment->amount_cents);
        $this->assertSame('succeeded', $payment->status);
        $this->assertSame('signup', $payment->metadata['trigger']);
        $this->assertSame('pro', $payment->metadata['plan_slug']);
    }

    public function test_changing_a_tenants_plan_records_a_plan_change_mock_payment(): void
    {
        $tenant = $this->actingAsTenant();

        $owner = Admin::factory()->create(['is_tenant_owner' => true, 'is_active' => true]);
        $newPlan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Enterprise', slug: 'enterprise', priceCents: 29900, features: [], limits: [], isActive: true, isPublic: false
        );

        $token = $owner->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/tenant/subscription-plan', ['subscription_plan_id' => $newPlan->id])
            ->assertStatus(200);

        $payment = MockPayment::on('landlord')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(29900, $payment->amount_cents);
        $this->assertSame('plan_change', $payment->metadata['trigger']);
    }

    public function test_a_plan_with_no_price_records_a_zero_amount_payment(): void
    {
        $freePlan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Free', slug: 'free', priceCents: null, features: [], limits: [], isActive: true, isPublic: true
        );

        $tenant = app(ProvisionTenantUseCase::class)->execute(
            name: 'Free Co',
            subdomain: 'freeco',
            subscriptionPlanId: $freePlan->id,
            createdVia: 'self_service',
            adminEmail: 'owner@freeco.test',
            adminPassword: 'super-secret',
        );

        $payment = MockPayment::on('landlord')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(0, $payment->amount_cents);
    }
}
