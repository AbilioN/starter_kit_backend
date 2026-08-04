<?php

namespace Tests\Feature\Tenant;

use Tests\TenantTestCase;

/**
 * Local-dev-only convenience: real subdomains need /etc/hosts or wildcard
 * DNS, which not everyone has configured. IdentifyTenant falls back to a
 * ?tenant= query param when the subdomain doesn't resolve, but only in
 * local/testing environments - this is the guarantee that matters most,
 * since letting this leak into production would undermine subdomain-based
 * tenant isolation entirely.
 */
class TenantQueryParamFallbackTest extends TenantTestCase
{
    public function test_query_param_resolves_the_tenant_when_no_real_subdomain_is_present(): void
    {
        $this->actingAsTenant('acme');

        // useTenantHost() would normally set a resolvable "acme.example.test"
        // Host header - reset it to a plain, non-tenant host (matching what
        // a browser hitting bare "localhost:8006" actually sends) to prove
        // resolution here comes from the query param, not the subdomain.
        $this->useTenantHost('localhost');

        $this->getJson('/api/tenant/theme?tenant=acme')
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Testing Tenant');
    }

    public function test_request_without_the_query_param_still_404s(): void
    {
        $this->actingAsTenant('acme');
        $this->useTenantHost('localhost');

        $this->getJson('/api/tenant/theme')
            ->assertStatus(404)
            ->assertJson(['message' => 'Tenant not found.']);
    }
}
