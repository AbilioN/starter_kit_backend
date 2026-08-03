<?php

namespace Tests\Feature\GodAdmin;

use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Livewire\SubscriptionPlans\Form;
use App\Models\GodAdmin;
use Livewire\Livewire;
use Tests\TenantTestCase;

class SubscriptionPlanManagementTest extends TenantTestCase
{
    private GodAdmin $godAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenant();

        $this->godAdmin = GodAdmin::create([
            'name' => 'Root',
            'email' => 'root@starterkit.test',
            'password' => 'secret-password',
        ]);
    }

    public function test_godadmin_can_create_a_subscription_plan(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class)
            ->set('name', 'Starter')
            ->set('slug', 'starter')
            ->set('priceCents', 4900)
            ->set('featureChat', true)
            ->set('maxAdmins', 3)
            ->call('save')
            ->assertRedirect('/god/subscription-plans');

        $plan = app(SubscriptionPlanRepositoryInterface::class)->findBySlug('starter');
        $this->assertNotNull($plan);
        $this->assertSame(4900, $plan->priceCents);
        $this->assertTrue($plan->features['chat']);
        $this->assertSame(3, $plan->limits['max_admins']);
    }

    public function test_godadmin_can_edit_an_existing_plan(): void
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Starter',
            slug: 'starter',
            priceCents: 4900,
            features: ['chat' => false],
            limits: ['max_admins' => 3],
        );

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class, ['planId' => $plan->id])
            ->assertSet('name', 'Starter')
            ->set('name', 'Starter Pro')
            ->set('featureChat', true)
            ->call('save')
            ->assertRedirect('/god/subscription-plans');

        $updated = app(SubscriptionPlanRepositoryInterface::class)->findById($plan->id);
        $this->assertSame('Starter Pro', $updated->name);
        $this->assertTrue($updated->features['chat']);
    }

    public function test_guest_cannot_access_subscription_plans(): void
    {
        $this->get('/god/subscription-plans')->assertRedirect('/god/login');
    }
}
