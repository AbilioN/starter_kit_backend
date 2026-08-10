<?php

namespace Tests\Feature\Tenant;

use App\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Models\Admin;
use App\Models\Assistant;
use Tests\TenantTestCase;

/**
 * Covers SyncAgentProfilesForTenantUseCase: provisioning/plan-change
 * activates/deactivates the tenant's own Assistant rows to match whichever
 * AgentProfiles are assigned to the tenant's plan — never touching manually
 * seeded assistants (agent_profile_id null).
 */
class AgentProfileSyncTest extends TenantTestCase
{
    private function makeProfile(string $name = 'Support Bot'): string
    {
        return app(AgentProfileRepositoryInterface::class)->create(
            name: $name,
            description: 'Helps with support questions',
            avatar: null,
            systemPrompt: 'You are a helpful support agent.',
            model: 'gpt-4o-mini',
        )->id;
    }

    public function test_provisioning_a_tenant_activates_the_plans_agent_profiles(): void
    {
        $profileId = $this->makeProfile();
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro', slug: 'pro', priceCents: null, features: [], limits: [],
        );
        app(AgentProfileRepositoryInterface::class)->syncPlans($profileId, [$plan->id]);

        $this->artisan('tenant:provision', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            '--plan' => $plan->id,
            '--admin-email' => 'owner@acme.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        $this->useTenantHost('acme');
        $assistant = Assistant::where('agent_profile_id', $profileId)->first();

        $this->assertNotNull($assistant);
        $this->assertTrue($assistant->is_active);
        $this->assertSame('Support Bot', $assistant->name);
    }

    public function test_changing_plan_deactivates_agents_no_longer_available_and_activates_new_ones(): void
    {
        $oldProfileId = $this->makeProfile('Old Bot');
        $newProfileId = $this->makeProfile('New Bot');

        $oldPlan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Old Plan', slug: 'old-plan', priceCents: null, features: [], limits: [],
        );
        $newPlan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'New Plan', slug: 'new-plan', priceCents: null, features: [], limits: [],
        );
        app(AgentProfileRepositoryInterface::class)->syncPlans($oldProfileId, [$oldPlan->id]);
        app(AgentProfileRepositoryInterface::class)->syncPlans($newProfileId, [$newPlan->id]);

        $this->artisan('tenant:provision', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            '--plan' => $oldPlan->id,
            '--admin-email' => 'owner@acme.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        $tenant = app(TenantRepositoryInterface::class)->findBySubdomain('acme');
        $this->useTenantHost('acme');
        $owner = Admin::where('email', 'owner@acme.test')->first();
        $token = $owner->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/tenant/subscription-plan', ['subscription_plan_id' => $newPlan->id])
            ->assertStatus(200);

        $old = Assistant::where('agent_profile_id', $oldProfileId)->first();
        $new = Assistant::where('agent_profile_id', $newProfileId)->first();

        $this->assertFalse($old->is_active);
        $this->assertNotNull($new);
        $this->assertTrue($new->is_active);
    }

    public function test_sync_never_touches_manually_seeded_assistants(): void
    {
        $this->actingAsTenant('acme');

        $manual = Assistant::create([
            'name' => 'Manual Demo Assistant',
            'description' => 'Seeded by hand, not by a profile',
            'is_active' => true,
        ]);

        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Basic', slug: 'basic', priceCents: null, features: [], limits: [],
        );

        app(\App\Application\UseCases\Tenant\SyncAgentProfilesForTenantUseCase::class)->execute($plan->id);

        $this->assertNull($manual->fresh()->agent_profile_id);
        $this->assertTrue($manual->fresh()->is_active);
    }
}
