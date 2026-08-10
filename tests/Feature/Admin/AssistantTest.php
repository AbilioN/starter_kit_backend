<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Assistant;
use Tests\TenantTestCase;

class AssistantTest extends TenantTestCase
{
    public function test_admin_can_list_active_assistants(): void
    {
        $this->actingAsTenant('acme');

        Assistant::create(['name' => 'Active Bot', 'is_active' => true]);
        Assistant::create(['name' => 'Inactive Bot', 'is_active' => false]);

        $admin = Admin::factory()->create(['is_active' => true]);
        $token = $admin->createToken('t')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/assistants')
            ->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Active Bot', $names);
        $this->assertNotContains('Inactive Bot', $names);
    }

    public function test_assistants_are_isolated_per_tenant(): void
    {
        $this->artisan('tenant:provision', [
            'name' => 'Tenant A', 'subdomain' => 'tenant-a',
            '--admin-email' => 'owner@tenant-a.test', '--admin-password' => 'password-a',
        ])->assertExitCode(0);
        $this->artisan('tenant:provision', [
            'name' => 'Tenant B', 'subdomain' => 'tenant-b',
            '--admin-email' => 'owner@tenant-b.test', '--admin-password' => 'password-b',
        ])->assertExitCode(0);

        $this->useTenantHost('tenant-a');
        $this->postJson('/api/admin/login', ['email' => 'owner@tenant-a.test', 'password' => 'password-a'])
            ->assertStatus(200);
        // The login request above already routed through IdentifyTenant,
        // which points database.default at tenant-a for real - safe to
        // create directly now.
        Assistant::create(['name' => 'Tenant A Bot', 'is_active' => true]);

        $this->useTenantHost('tenant-b');
        $token = $this->postJson('/api/admin/login', ['email' => 'owner@tenant-b.test', 'password' => 'password-b'])
            ->assertStatus(200)->json('token');

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/assistants')
            ->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertNotContains('Tenant A Bot', $names);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->actingAsTenant('acme');

        $this->getJson('/api/admin/assistants')->assertStatus(401);
    }
}
