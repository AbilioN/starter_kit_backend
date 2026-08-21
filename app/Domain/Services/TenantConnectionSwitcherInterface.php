<?php

namespace App\Domain\Services;

interface TenantConnectionSwitcherInterface
{
    /**
     * Run $callback with the `tenant` connection pointed at $databaseName,
     * restoring the previous connection afterwards even if it throws.
     */
    public function run(string $databaseName, callable $callback): mixed;
}
