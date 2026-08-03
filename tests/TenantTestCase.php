<?php

namespace Tests;

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
}
