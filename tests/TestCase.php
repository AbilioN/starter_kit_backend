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
}
