<?php

namespace App\Domain\Services;

interface TenantProvisioningServiceInterface
{
    /**
     * Creates the physical database a new tenant will use. $databaseName is
     * whatever config('database.connections.tenant.database') expects for
     * the currently configured driver - a plain identifier for MySQL, a
     * file path for sqlite (tests).
     */
    public function createDatabase(string $databaseName): void;
}
