<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            return;
        }

        $dbPath = (string) config('database.connections.sqlite.database');

        if ($dbPath === '' || $dbPath === ':memory:') {
            return;
        }

        File::ensureDirectoryExists(dirname($dbPath));

        if (! File::exists($dbPath)) {
            File::put($dbPath, '');
        }
    }

    /**
     * All app migrations now live under database/migrations/tenant/ (see
     * docs/03-multitenancy-plan.md) - the default database/migrations/ path
     * is empty. RefreshDatabase's built-in migrate:fresh call needs pointing
     * at the new path, or every test using the default sqlite connection
     * (not just TenantTestCase ones) would migrate against an empty schema.
     */
    protected function migrateFreshUsing()
    {
        return array_merge(parent::migrateFreshUsing(), [
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
        ]);
    }
}
