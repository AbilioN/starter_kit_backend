<?php

namespace Tests\Feature\Tenant;

use App\Domain\Repositories\MockPaymentRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Models\Admin;
use Tests\TenantTestCase;

class SubscriptionHistoryTest extends TenantTestCase
{
    private function ownerToken(bool $isTenantOwner = true): string
    {
        $admin = Admin::factory()->create([
            'is_tenant_owner' => $isTenantOwner,
            'is_active' => true,
        ]);

        return $admin->createToken('t')->plainTextToken;
    }

    private function auth(string $token): self
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$token]);
    }

    public function test_tenant_owner_sees_their_payments_newest_first(): void
    {
        $tenant = $this->actingAsTenant();
        $plans = app(SubscriptionPlanRepositoryInterface::class);
        $payments = app(MockPaymentRepositoryInterface::class);

        $basico = $plans->create(name: 'Básico', slug: 'basico', priceCents: 1900, features: [], limits: []);
        $pro = $plans->create(name: 'Pro', slug: 'pro', priceCents: 9900, features: [], limits: []);

        $payments->record($tenant->id, $basico->id, 1900, ['trigger' => 'signup', 'plan_slug' => 'basico']);
        $payments->record($tenant->id, $pro->id, 9900, ['trigger' => 'plan_change', 'plan_slug' => 'pro']);

        $response = $this->auth($this->ownerToken())
            ->getJson('/api/admin/tenant/subscription-history')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'amount_cents', 'status', 'trigger', 'plan_id', 'plan_slug', 'plan_name', 'created_at']],
                'current_plan',
                'pagination' => ['total', 'per_page', 'current_page', 'last_page', 'from', 'to'],
            ]);

        $this->assertSame(2, $response->json('pagination.total'));

        $triggers = array_column($response->json('data'), 'trigger');
        $this->assertContains('signup', $triggers);
        $this->assertContains('plan_change', $triggers);

        $nomes = array_column($response->json('data'), 'plan_name');
        $this->assertContains('Pro', $nomes);
    }

    public function test_non_owner_admin_cannot_read_the_history(): void
    {
        $this->actingAsTenant();

        $this->auth($this->ownerToken(isTenantOwner: false))
            ->getJson('/api/admin/tenant/subscription-history')
            ->assertStatus(403);
    }

    public function test_current_plan_reflects_the_tenant_plan_and_is_null_when_unset(): void
    {
        $tenant = $this->actingAsTenant();
        $token = $this->ownerToken();

        $this->auth($token)->getJson('/api/admin/tenant/subscription-history')
            ->assertStatus(200)
            ->assertJsonPath('current_plan', null);

        $plan = app(SubscriptionPlanRepositoryInterface::class)
            ->create(name: 'Pro', slug: 'pro', priceCents: 9900, features: [], limits: []);
        app(TenantRepositoryInterface::class)->update($tenant->id, subscriptionPlanId: $plan->id);

        $this->auth($token)->getJson('/api/admin/tenant/subscription-history')
            ->assertStatus(200)
            ->assertJsonPath('current_plan.slug', 'pro')
            ->assertJsonPath('current_plan.price_cents', 9900);
    }

    /**
     * Esta rota lê uma tabela do LANDLORD a partir de um contexto de tenant —
     * é o único ponto onde vazamento entre tenants seria possível. O tenant id
     * vem só de app('currentTenant'), nunca de um parâmetro.
     */
    public function test_payments_of_another_tenant_are_never_returned(): void
    {
        $tenantA = $this->actingAsTenant('tenant-a');
        $payments = app(MockPaymentRepositoryInterface::class);
        $plan = app(SubscriptionPlanRepositoryInterface::class)
            ->create(name: 'Pro', slug: 'pro', priceCents: 9900, features: [], limits: []);

        $payments->record($tenantA->id, $plan->id, 9900, ['trigger' => 'signup', 'plan_slug' => 'pro']);

        // Um SEGUNDO tenant real (mock_payments.tenant_id tem FK para tenants),
        // com pagamento próprio no mesmo banco landlord.
        $tenantB = app(TenantRepositoryInterface::class)->create(
            name: 'Tenant B',
            subdomain: 'tenant-b',
            databaseName: 'tenant_b_db',
            subscriptionPlanId: $plan->id,
            createdVia: 'godadmin',
            status: 'active',
        );
        $payments->record($tenantB->id, $plan->id, 12345, ['trigger' => 'signup', 'plan_slug' => 'pro']);

        $response = $this->auth($this->ownerToken())
            ->getJson('/api/admin/tenant/subscription-history')
            ->assertStatus(200);

        $this->assertSame(1, $response->json('pagination.total'));
        $this->assertNotContains(12345, array_column($response->json('data'), 'amount_cents'));
    }

    /** FK nullOnDelete: sem plano, o slug guardado no metadata ainda identifica a linha. */
    public function test_payment_whose_plan_was_deleted_still_renders(): void
    {
        $tenant = $this->actingAsTenant();
        $payments = app(MockPaymentRepositoryInterface::class);

        $payments->record($tenant->id, null, 4900, ['trigger' => 'plan_change', 'plan_slug' => 'plano-extinto']);

        $response = $this->auth($this->ownerToken())
            ->getJson('/api/admin/tenant/subscription-history')
            ->assertStatus(200);

        $this->assertSame(1, $response->json('pagination.total'));
        $this->assertNull($response->json('data.0.plan_id'));
        $this->assertNull($response->json('data.0.plan_name'));
        $this->assertSame('plano-extinto', $response->json('data.0.plan_slug'));
    }
}
