<?php

namespace Tests\Integration\Tenant;

use Tests\TenantTestCase;

/**
 * The single most important correctness property of the whole
 * multitenancy migration: data written for one tenant must never be
 * visible while resolved as a different tenant. Provisions two REAL,
 * separately-provisioned tenants (not the shared fixture from
 * actingAsTenant()) and drives everything through real HTTP requests
 * resolved via IdentifyTenant - not direct connection manipulation -
 * so this exercises the exact mechanism production traffic uses.
 */
class TenantIsolationIntegrationTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('tenant:provision', [
            'name' => 'Tenant A',
            'subdomain' => 'tenant-a',
            '--admin-email' => 'owner@tenant-a.test',
            '--admin-password' => 'password-a',
        ])->assertExitCode(0);

        $this->artisan('tenant:provision', [
            'name' => 'Tenant B',
            'subdomain' => 'tenant-b',
            '--admin-email' => 'owner@tenant-b.test',
            '--admin-password' => 'password-b',
        ])->assertExitCode(0);
    }

    public function test_each_tenant_admin_can_only_log_into_their_own_subdomain(): void
    {
        $this->useTenantHost('tenant-a');
        $this->postJson('/api/admin/login', [
            'email' => 'owner@tenant-a.test',
            'password' => 'password-a',
        ])->assertStatus(200);

        // Tenant A's credentials don't exist on Tenant B's database.
        $this->useTenantHost('tenant-b');
        $this->postJson('/api/admin/login', [
            'email' => 'owner@tenant-a.test',
            'password' => 'password-a',
        ])->assertStatus(401);

        $this->postJson('/api/admin/login', [
            'email' => 'owner@tenant-b.test',
            'password' => 'password-b',
        ])->assertStatus(200);

        $this->useTenantHost('tenant-a');
        $this->postJson('/api/admin/login', [
            'email' => 'owner@tenant-b.test',
            'password' => 'password-b',
        ])->assertStatus(401);
    }

    public function test_admin_list_on_tenant_a_only_shows_tenant_as_own_admin(): void
    {
        $this->assertAdminListOnlyShowsOwnAdmin('tenant-a', 'owner@tenant-a.test', 'password-a');
    }

    public function test_admin_list_on_tenant_b_only_shows_tenant_bs_own_admin(): void
    {
        $this->assertAdminListOnlyShowsOwnAdmin('tenant-b', 'owner@tenant-b.test', 'password-b');
    }

    /**
     * Deliberately one tenant per test method, not one test doing both: the
     * Auth guard caches its resolved user within the container, and a
     * single test method reuses the same container across every
     * postJson()/getJson() call it makes. Authenticating as tenant A's
     * admin then immediately trying to log in as tenant B's within the same
     * method resolves Auth::user() to the STALE tenant-A admin during
     * tenant B's own last_login_at update - triggering an unrelated
     * pre-existing bug (HasAuditLog resolves the audit actor from
     * Auth::user() rather than the model instance being updated, and
     * LogAuditUseCase's $userId is typed int while Admin uses UUIDs).
     * Splitting into separate methods gives each a fresh container via
     * refreshApplication(), sidestepping the cross-contamination entirely.
     */
    private function assertAdminListOnlyShowsOwnAdmin(string $subdomain, string $email, string $password): void
    {
        $this->useTenantHost($subdomain);
        $token = $this->postJson('/api/admin/login', [
            'email' => $email,
            'password' => $password,
        ])->assertStatus(200)->json('token');

        $admins = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/admins')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $admins);
        $this->assertSame($email, $admins[0]['email']);
    }
}
