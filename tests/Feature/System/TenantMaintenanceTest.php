<?php

namespace Tests\Feature\System;

use App\Console\Commands\CheckTenantDatabasesCommand;
use App\Models\Admin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * The scheduled per-tenant maintenance (roadmap 5.2) and how the readiness
 * probe reports it.
 */
class TenantMaintenanceTest extends TenantTestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant();
        Cache::forget(CheckTenantDatabasesCommand::CACHE_KEY);

        $this->admin = Admin::create([
            'name' => 'Owner',
            'email' => 'owner@tenant.test',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'is_tenant_owner' => true,
        ]);
    }

    public function test_pruning_deletes_expired_tokens(): void
    {
        $this->admin->createToken('expired', ['*'], now()->subDays(3));

        $this->artisan('tenant:prune-tokens')->assertSuccessful();

        $this->assertSame(0, DB::connection('tenant')->table('personal_access_tokens')->count());
    }

    public function test_pruning_keeps_tokens_that_are_still_valid(): void
    {
        $this->admin->createToken('live', ['*'], now()->addHour());

        $this->artisan('tenant:prune-tokens')->assertSuccessful();

        $this->assertSame(1, DB::connection('tenant')->table('personal_access_tokens')->count());
    }

    public function test_pruning_keeps_tokens_that_never_expire(): void
    {
        // Ordinary admin logins have no expiry at all. Deleting those would log
        // every customer out nightly.
        $this->admin->createToken('admin-api');

        $this->artisan('tenant:prune-tokens')->assertSuccessful();

        $this->assertSame(1, DB::connection('tenant')->table('personal_access_tokens')->count());
    }

    public function test_pruning_respects_the_grace_period(): void
    {
        // Just-expired tokens are still useful evidence while someone is asking
        // "why was I logged out".
        $this->admin->createToken('recently-expired', ['*'], now()->subMinutes(5));

        $this->artisan('tenant:prune-tokens --hours=24')->assertSuccessful();

        $this->assertSame(1, DB::connection('tenant')->table('personal_access_tokens')->count());
    }

    public function test_the_health_check_caches_its_result(): void
    {
        $this->artisan('tenant:health-check')->assertSuccessful();

        $cached = Cache::get(CheckTenantDatabasesCommand::CACHE_KEY);

        $this->assertIsArray($cached);
        $this->assertSame(1, $cached['total']);
        $this->assertSame([], $cached['failed']);
    }

    public function test_readiness_says_so_when_the_check_has_never_run(): void
    {
        // Distinct from "ok". A probe that reports healthy for a check that was
        // never performed is the exact failure this design avoids.
        $this->getJson('/api/health/ready')
            ->assertJsonPath('checks.tenant_databases.status', 'skipped');
    }

    public function test_readiness_reports_a_healthy_check(): void
    {
        $this->artisan('tenant:health-check');

        $this->getJson('/api/health/ready')
            ->assertJsonPath('checks.tenant_databases.status', 'ok')
            ->assertJsonPath('checks.tenant_databases.unreachable', 0);
    }

    public function test_readiness_degrades_when_the_check_stopped_running(): void
    {
        // A cron that died looks identical to a healthy system if you only read
        // the last verdict and not its age.
        $maxAge = (int) config('health.tenant_databases.max_age_minutes');

        Cache::forever(CheckTenantDatabasesCommand::CACHE_KEY, [
            'checked_at' => now()->subMinutes($maxAge + 10)->toISOString(),
            'total' => 3,
            'failed' => [],
        ]);

        $response = $this->getJson('/api/health/ready');

        $response->assertJsonPath('checks.tenant_databases.status', 'degraded');
        $this->assertStringContainsString(
            'last checked',
            implode(' ', $response->json('checks.tenant_databases.problems')),
        );
    }

    public function test_readiness_names_the_unreachable_tenants(): void
    {
        Cache::forever(CheckTenantDatabasesCommand::CACHE_KEY, [
            'checked_at' => now()->toISOString(),
            'total' => 3,
            'failed' => [['subdomain' => 'acme', 'error' => 'connection refused']],
        ]);

        $response = $this->getJson('/api/health/ready');

        $response->assertJsonPath('checks.tenant_databases.status', 'degraded');
        $response->assertJsonPath('checks.tenant_databases.unreachable', 1);
        // Which tenant, not just how many — the first question an operator asks.
        $this->assertStringContainsString('acme', implode(' ', $response->json('checks.tenant_databases.problems')));
    }
}
