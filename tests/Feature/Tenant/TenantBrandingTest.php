<?php

namespace Tests\Feature\Tenant;

use App\Models\Admin;
use Tests\TenantTestCase;

class TenantBrandingTest extends TenantTestCase
{
    public function test_tenant_owner_can_update_branding(): void
    {
        $this->actingAsTenant('acme');

        $owner = Admin::factory()->create(['is_tenant_owner' => true, 'is_active' => true]);
        $token = $owner->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/tenant/branding', [
                'theme_primary_color' => '#112233',
                'theme_secondary_color' => '#445566',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.theme_primary_color', '#112233')
            ->assertJsonPath('data.theme_secondary_color', '#445566');
    }

    public function test_branding_update_rejects_invalid_color_format(): void
    {
        $this->actingAsTenant('acme');

        $owner = Admin::factory()->create(['is_tenant_owner' => true, 'is_active' => true]);
        $token = $owner->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/tenant/branding', ['theme_primary_color' => 'not-a-color'])
            ->assertStatus(422);
    }

    public function test_theme_endpoint_is_public_and_returns_current_tenant_branding(): void
    {
        $this->actingAsTenant('acme');

        $owner = Admin::factory()->create(['is_tenant_owner' => true, 'is_active' => true]);
        $token = $owner->createToken('t')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/tenant/branding', ['theme_primary_color' => '#abcdef']);

        // withHeaders() persists Authorization for the rest of the test
        // unless cleared - flush it so this call is genuinely anonymous,
        // proving the endpoint doesn't require auth.
        $this->flushHeaders();
        $this->getJson('/api/tenant/theme')
            ->assertStatus(200)
            ->assertJsonPath('data.primary_color', '#abcdef')
            ->assertJsonPath('data.name', 'Testing Tenant');
    }
}
