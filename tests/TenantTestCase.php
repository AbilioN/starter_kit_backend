<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

    /**
     * The default connection is named 'sqlite' in the normal (SQLite) run and
     * 'mysql' when the suite runs against the engine production uses
     * (phpunit.mysql.xml). Hardcoding 'sqlite' here made every test under the
     * MySQL config try to open a *file* named after the MySQL database, so the
     * whole suite errored before reaching a single assertion.
     */
    protected function connectionsToTransact()
    {
        return [config('database.default'), 'landlord', 'tenant'];
    }

    private static bool $landlordAndTenantMigrated = false;

    private ?string $actingTenantHost = null;

    /**
     * Database names the MySQL cleanup must never drop, captured before a test
     * can move them. Provisioning repoints `database.connections.tenant.database`
     * at the tenant it just created, so reading this at teardown time would
     * leave the suite's own tenant database unprotected — and drop it.
     */
    private array $protectedDatabases = [];

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

    /**
     * Undoes what a transaction cannot, when the suite runs on MySQL.
     *
     * RefreshDatabase relies on wrapping each test in a transaction and rolling
     * it back. SQLite makes DDL transactional, so a test that provisions a
     * tenant (CREATE DATABASE + migrations) rolls back like anything else. MySQL
     * **implicitly commits** on DDL: the moment a test provisions a tenant, the
     * enclosing transaction is gone and everything written so far is permanent.
     *
     * The visible symptom is not the provisioning test failing — it is the next
     * hundred tests failing on duplicate keys for rows they never inserted.
     *
     * So on MySQL only, wipe what leaked: the databases the test created, and
     * the rows left behind on landlord and tenant.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->protectedDatabases = array_filter([
            config('database.connections.tenant.database'),
            config('database.connections.landlord.database'),
            config('database.connections.'.config('database.default').'.database'),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->usesTransactionalDdl()) {
            parent::tearDown();

            return;
        }

        $this->dropTenantDatabasesCreatedDuringTest();
        $this->truncateAll('landlord');
        $this->truncateAll('tenant');

        parent::tearDown();
    }

    private function usesTransactionalDdl(): bool
    {
        return config('database.connections.'.config('database.default').'.driver') === 'sqlite';
    }

    /**
     * Only databases named by tenant rows this test created, never a blanket
     * "drop everything matching the prefix" — the MySQL server that runs the
     * suite may be the same one holding real development tenants.
     */
    private function dropTenantDatabasesCreatedDuringTest(): void
    {
        $protected = $this->protectedDatabases;

        try {
            $databases = DB::connection('landlord')->table('tenants')->pluck('database_name');
        } catch (\Throwable) {
            return;
        }

        foreach ($databases as $database) {
            if (! $database || in_array($database, $protected, true)) {
                continue;
            }

            try {
                DB::connection('landlord')->statement("DROP DATABASE IF EXISTS `{$database}`");
            } catch (\Throwable) {
                // A database that cannot be dropped must not fail the test that
                // already passed; the next run's provisioning will overwrite it.
            }
        }
    }

    private function truncateAll(string $connection): void
    {
        try {
            $tables = DB::connection($connection)->getSchemaBuilder()->getTableListing();
        } catch (\Throwable) {
            return;
        }

        DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            $table = str_contains($table, '.') ? explode('.', $table)[1] : $table;

            // `migrations` holds the schema state that beforeRefreshingDatabase
            // builds once per process — truncating it would make every later
            // test re-migrate from nothing.
            if ($table === 'migrations') {
                continue;
            }

            try {
                DB::connection($connection)->table($table)->truncate();
            } catch (\Throwable) {
                // Ignore: a table that vanished with a dropped database.
            }
        }

        DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function ensureSqliteFileExists(string $connection): void
    {
        // Under the MySQL config this value is a database name, not a path —
        // without the driver check it would create a junk file named after the
        // database in the project root.
        if (config("database.connections.{$connection}.driver") !== 'sqlite') {
            return;
        }

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
