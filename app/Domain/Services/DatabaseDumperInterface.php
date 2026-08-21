<?php

namespace App\Domain\Services;

/**
 * Dumps and restores a single database, given that database's connection
 * config.
 *
 * An interface rather than a `mysqldump` call inlined into the use case for two
 * reasons, and only the second is the usual one:
 *
 *  - **The test suite runs on SQLite while production runs MySQL** (phpunit.xml).
 *    A test that "proves the backup works" against SQLite proves nothing about
 *    the shell command that runs in production — this is the same trap that hid
 *    a completely broken notification system behind a green suite in 2026-08-21.
 *    Keeping the shell-out behind a seam makes it honest: the tests exercise the
 *    orchestration, and the dumper itself is verified against the real MySQL
 *    container.
 *  - A future Postgres or managed-snapshot implementation drops in here.
 */
interface DatabaseDumperInterface
{
    /**
     * Writes an uncompressed SQL dump of $databaseName to $targetPath.
     *
     * $connectionName names the connection whose *credentials* to use — the
     * database itself is always the explicit $databaseName. The landlord and a
     * tenant may sit on different servers, and neither is reachable through the
     * other's credentials.
     *
     * @throws \App\Domain\Exceptions\BackupFailedException
     */
    public function dump(string $databaseName, string $targetPath, string $connectionName = 'tenant'): void;

    /**
     * Replays $sourcePath into $databaseName, creating it if needed.
     *
     * Never called against a live tenant database — RestoreTenantBackupUseCase
     * restores into a new one and flips tenants.database_name afterwards.
     *
     * @throws \App\Domain\Exceptions\BackupFailedException
     */
    public function restore(string $databaseName, string $sourcePath, string $connectionName = 'tenant'): void;

    /**
     * Whether the underlying tooling is actually present. Checked up front so
     * a missing binary is one clear message instead of N cryptic per-tenant
     * failures.
     */
    public function isAvailable(): bool;
}
