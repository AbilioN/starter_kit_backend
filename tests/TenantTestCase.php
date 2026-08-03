<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Base test case for tests that need both the `landlord` and `tenant`
 * database connections available (in addition to the default `sqlite`
 * connection used by the rest of the suite).
 *
 * `landlord`/`tenant` schemas are built once per process via
 * beforeRefreshingDatabase() — the RefreshDatabase hook that runs before
 * transactions are opened. Per-test isolation then comes from
 * RefreshDatabase wrapping all three connections in transactions that
 * roll back after each test, same as it already does for the default
 * connection.
 */
abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite', 'landlord', 'tenant'];

    private static bool $landlordAndTenantMigrated = false;

    private ?string $actingTenantHost = null;

    /**
     * Overrides MakesHttpRequests::prepareUrlForRequest() so every existing
     * getJson()/postJson()/etc. call across the suite keeps working
     * unchanged. Laravel's default implementation builds requests against
     * config('app.url') (e.g. "localhost"), and Symfony's Request::create()
     * unconditionally derives HTTP_HOST from that URL's embedded host -
     * overriding anything set via withServerVariables(). The only reliable
     * way to control the resolved host is to embed it directly in the URL
     * passed to Request::create(), which is what this override does.
     */
    protected function prepareUrlForRequest($uri)
    {
        if (! $this->actingTenantHost) {
            return parent::prepareUrlForRequest($uri);
        }

        $uri = $uri instanceof \Illuminate\Support\Uri ? $uri->value() : $uri;

        if (str_starts_with($uri, '/')) {
            $uri = substr($uri, 1);
        }

        return trim("http://{$this->actingTenantHost}/{$uri}", '/');
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->ensureSqliteFileExists('landlord');
        $this->ensureSqliteFileExists('tenant');

        if (static::$landlordAndTenantMigrated) {
            return;
        }

        Artisan::call('migrate:fresh', [
            '--database' => 'landlord',
            '--path' => 'database/migrations/landlord',
            '--realpath' => false,
        ]);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
        ]);

        static::$landlordAndTenantMigrated = true;
    }

    private function ensureSqliteFileExists(string $connection): void
    {
        $path = config("database.connections.{$connection}.database");

        if (! $path || $path === ':memory:') {
            return;
        }

        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path)) {
            File::put($path, '');
        }
    }

    /**
     * Creates a landlord Tenant row and points every subsequent request in
     * this test at it (via the Host header) and every subsequent unqualified
     * query at it (via database.default) - the same thing IdentifyTenant
     * does per-request in production, just done once up front for tests.
     *
     * database_name is set to the connection's own already-configured
     * database, so IdentifyTenant's "only purge if the target actually
     * changes" check is a no-op here and the test's open transaction (and
     * anything written to it before this call) survives real HTTP requests
     * made later in the same test.
     */
    protected function actingAsTenant(string $subdomain = 'testing'): Tenant
    {
        config(['database.default' => 'tenant']);

        $tenant = Tenant::create([
            'name' => 'Testing Tenant',
            'subdomain' => $subdomain,
            'database_name' => config('database.connections.tenant.database'),
            'status' => 'active',
            'created_via' => 'godadmin',
        ]);

        $this->actingTenantHost = "{$subdomain}.example.test";

        return $tenant;
    }

    /**
     * Points subsequent HTTP requests at an already-provisioned tenant's
     * subdomain (e.g. one created via ProvisionTenantUseCase), without
     * creating a new Tenant row or touching database.default - useful for
     * tests that need to simulate real, separate requests hitting distinct
     * tenants and rely on IdentifyTenant itself to do the connection switch.
     */
    protected function useTenantHost(string $subdomain): void
    {
        $this->actingTenantHost = "{$subdomain}.example.test";
    }
}
