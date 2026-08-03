<?php

namespace App\Infrastructure\Services;

use App\Domain\Services\TenantProvisioningServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class TenantProvisioningService implements TenantProvisioningServiceInterface
{
    public function createDatabase(string $databaseName): void
    {
        if (config('database.connections.tenant.driver') === 'sqlite') {
            File::ensureDirectoryExists(dirname($databaseName));

            if (! File::exists($databaseName)) {
                File::put($databaseName, '');
            }

            return;
        }

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
            throw new InvalidArgumentException("Invalid tenant database name: {$databaseName}");
        }

        DB::connection('landlord')->statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}`");
    }
}
