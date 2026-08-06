<?php

namespace Tests\Feature\Public;

use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Models\MockPayment;
use App\Models\Tenant;
use Tests\TenantTestCase;

class PublicTenantSignupTest extends TenantTestCase
{
    public function test_signup_with_a_public_plan_provisions_a_tenant_and_records_a_mock_payment(): void
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Starter', slug: 'starter', priceCents: 1900, features: ['chat' => true], limits: [], isActive: true, isPublic: true
        );

        $response = $this->postJson('/api/public/signup', [
            'name' => 'Public Signup Co',
            'subdomain' => 'publicsignup',
            'plan_id' => $plan->id,
            'admin_email' => 'owner@publicsignup.test',
            'admin_password' => 'super-secret',
            'admin_password_confirmation' => 'super-secret',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.subdomain', 'publicsignup');

        $tenant = Tenant::on('landlord')->where('subdomain', 'publicsignup')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('self_service', $tenant->created_via);
        $this->assertSame('active', $tenant->status);

        $payment = MockPayment::on('landlord')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(1900, $payment->amount_cents);
        $this->assertSame('signup', $payment->metadata['trigger']);
    }

    public function test_signup_without_a_plan_provisions_a_tenant_with_no_mock_payment(): void
    {
        $response = $this->postJson('/api/public/signup', [
            'name' => 'No Plan Co',
            'subdomain' => 'noplan',
            'admin_email' => 'owner@noplan.test',
            'admin_password' => 'super-secret',
            'admin_password_confirmation' => 'super-secret',
        ]);

        $response->assertStatus(201);

        $tenant = Tenant::on('landlord')->where('subdomain', 'noplan')->first();
        $this->assertNotNull($tenant);
        $this->assertSame(0, MockPayment::on('landlord')->where('tenant_id', $tenant->id)->count());
    }

    public function test_signup_rejects_a_private_plan_id(): void
    {
        $privatePlan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Partner Deal', slug: 'partner-deal', priceCents: 500, features: [], limits: [], isActive: true, isPublic: false
        );

        $response = $this->postJson('/api/public/signup', [
            'name' => 'Sneaky Co',
            'subdomain' => 'sneaky',
            'plan_id' => $privatePlan->id,
            'admin_email' => 'owner@sneaky.test',
            'admin_password' => 'super-secret',
            'admin_password_confirmation' => 'super-secret',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('plan_id');
        $this->assertNull(Tenant::on('landlord')->where('subdomain', 'sneaky')->first());
    }

    public function test_signup_rejects_a_duplicate_subdomain(): void
    {
        $this->postJson('/api/public/signup', [
            'name' => 'First Co',
            'subdomain' => 'dupe',
            'admin_email' => 'owner@first.test',
            'admin_password' => 'super-secret',
            'admin_password_confirmation' => 'super-secret',
        ])->assertStatus(201);

        $response = $this->postJson('/api/public/signup', [
            'name' => 'Second Co',
            'subdomain' => 'dupe',
            'admin_email' => 'owner@second.test',
            'admin_password' => 'super-secret',
            'admin_password_confirmation' => 'super-secret',
        ]);

        // Caught by the FormRequest's `unique:landlord.tenants,subdomain` rule
        // before the use case's DomainException path is ever reached - same
        // standard Laravel validation-error shape as any other 422.
        $response->assertStatus(422)->assertJsonValidationErrors('subdomain');
    }
}
