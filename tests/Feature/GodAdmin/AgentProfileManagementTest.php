<?php

namespace Tests\Feature\GodAdmin;

use App\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Livewire\AgentProfiles\Form;
use App\Livewire\AgentProfiles\Index;
use App\Models\Assistant;
use App\Models\GodAdmin;
use App\Models\Tenant;
use Livewire\Livewire;
use Tests\TenantTestCase;

class AgentProfileManagementTest extends TenantTestCase
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

    public function test_godadmin_can_create_an_agent_profile(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class)
            ->set('name', 'Support Bot')
            ->set('description', 'Helps with support')
            ->set('systemPrompt', 'You are a support agent.')
            ->set('modelPreset', 'gpt-4o-mini')
            ->call('save')
            ->assertHasNoErrors();

        $profiles = app(AgentProfileRepositoryInterface::class)->findAll();
        $this->assertCount(1, $profiles);
        $this->assertSame('Support Bot', $profiles[0]->name);
        $this->assertSame('gpt-4o-mini', $profiles[0]->model);
    }

    public function test_godadmin_can_set_a_custom_model_not_in_the_preset_list(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class)
            ->set('name', 'Bleeding Edge Bot')
            ->set('modelPreset', 'custom')
            ->set('modelCustom', 'gpt-4o-2026-08-01')
            ->call('save')
            ->assertHasNoErrors();

        $profile = app(AgentProfileRepositoryInterface::class)->findAll()[0];
        $this->assertSame('gpt-4o-2026-08-01', $profile->model);
    }

    public function test_selecting_custom_without_typing_a_value_fails_validation(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class)
            ->set('name', 'Bleeding Edge Bot')
            ->set('modelPreset', 'custom')
            ->set('modelCustom', '')
            ->call('save')
            ->assertHasErrors(['modelCustom' => 'required_if']);
    }

    public function test_leaving_model_as_inherit_stores_null(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class)
            ->set('name', 'Generic Bot')
            ->call('save')
            ->assertHasNoErrors();

        $profile = app(AgentProfileRepositoryInterface::class)->findAll()[0];
        $this->assertNull($profile->model);
    }

    public function test_editing_a_profile_with_a_non_preset_model_starts_in_custom_mode(): void
    {
        $profile = app(AgentProfileRepositoryInterface::class)->create(
            name: 'Bleeding Edge Bot', description: null, avatar: null, systemPrompt: null,
            model: 'gpt-4o-2026-08-01',
        );

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class, ['profileId' => $profile->id])
            ->assertSet('modelPreset', 'custom')
            ->assertSet('modelCustom', 'gpt-4o-2026-08-01');
    }

    public function test_assigning_a_plan_activates_the_agent_for_already_provisioned_tenants(): void
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro', slug: 'pro', priceCents: null, features: [], limits: [],
        );

        $this->artisan('tenant:provision', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            '--plan' => $plan->id,
            '--admin-email' => 'owner@acme.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class)
            ->set('name', 'Support Bot')
            ->set('selectedPlanIds', [$plan->id])
            ->call('save')
            ->assertHasNoErrors();

        $profile = app(AgentProfileRepositoryInterface::class)->findAll()[0];

        $this->useTenantHost('acme');
        $assistant = Assistant::where('agent_profile_id', $profile->id)->first();
        $this->assertNotNull($assistant);
        $this->assertTrue($assistant->is_active);
    }

    public function test_removing_a_plan_from_an_existing_profile_deactivates_it_for_tenants_on_that_plan(): void
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro', slug: 'pro', priceCents: null, features: [], limits: [],
        );
        $profile = app(AgentProfileRepositoryInterface::class)->create(
            name: 'Support Bot', description: null, avatar: null, systemPrompt: null, model: null,
        );
        app(AgentProfileRepositoryInterface::class)->syncPlans($profile->id, [$plan->id]);

        $this->artisan('tenant:provision', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            '--plan' => $plan->id,
            '--admin-email' => 'owner@acme.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class, ['profileId' => $profile->id])
            ->assertSet('selectedPlanIds', [$plan->id])
            ->set('selectedPlanIds', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->useTenantHost('acme');
        $assistant = Assistant::where('agent_profile_id', $profile->id)->first();
        $this->assertNotNull($assistant);
        $this->assertFalse($assistant->is_active);
    }

    public function test_godadmin_can_delete_a_profile_and_it_deactivates_everywhere(): void
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro', slug: 'pro', priceCents: null, features: [], limits: [],
        );
        $profile = app(AgentProfileRepositoryInterface::class)->create(
            name: 'Support Bot', description: null, avatar: null, systemPrompt: null, model: null,
        );
        app(AgentProfileRepositoryInterface::class)->syncPlans($profile->id, [$plan->id]);

        $this->artisan('tenant:provision', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            '--plan' => $plan->id,
            '--admin-email' => 'owner@acme.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Index::class)
            ->call('delete', $profile->id);

        $this->assertNull(app(AgentProfileRepositoryInterface::class)->findById($profile->id));

        $this->useTenantHost('acme');
        $assistant = Assistant::where('agent_profile_id', $profile->id)->first();
        $this->assertNotNull($assistant);
        $this->assertFalse($assistant->is_active);
    }

    public function test_guest_cannot_access_agent_profiles(): void
    {
        $this->get('/god/agent-profiles')->assertRedirect('/god/login');
    }
}
