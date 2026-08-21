<?php

namespace App\Infrastructure\Services;

use App\Domain\Exceptions\BackupFailedException;
use App\Domain\Services\DatabaseDumperInterface;
use Symfony\Component\Process\Process;

/**
 * mysqldump / mysql, driven through Symfony Process.
 *
 * `mysqldump` is NOT in the base php:8.2-fpm image — this class only works
 * because the Dockerfile installs `default-mysql-client`, which means the app,
 * horizon and scheduler images have to be REBUILT, not merely restarted, for
 * backups to work at all. isAvailable() exists so that shows up as one clear
 * message rather than as an identical failure on every tenant in the loop.
 */
class MysqlDatabaseDumper implements DatabaseDumperInterface
{
    public function isAvailable(): bool
    {
        $process = new Process([config('backup.mysqldump_path'), '--version']);
        $process->run();

        return $process->isSuccessful();
    }

    public function dump(string $databaseName, string $targetPath, string $connectionName = 'tenant'): void
    {
        $connection = config("database.connections.{$connectionName}");

        $process = new Process([
            config('backup.mysqldump_path'),
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? 3306),
            '--user='.($connection['username'] ?? 'root'),
            // --single-transaction takes a consistent snapshot without locking
            // the tables, so a nightly backup does not stall a live tenant.
            '--single-transaction',
            // Streams row by row instead of buffering the whole table in RAM;
            // without it a large tenant takes the container down rather than
            // taking a long time.
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--triggers',
            '--events',
            // Without this, a dump replayed into the *restore* database still
            // carries the original database name in its USE statement, and the
            // restore silently overwrites the live one instead.
            '--no-create-db',
            '--result-file='.$targetPath,
            $databaseName,
        ], env: [
            // Never on the argv: the full command line is visible to every
            // process on the host via /proc, and this one would carry the
            // database password.
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ]);

        $process->setTimeout(config('backup.timeout_seconds'));
        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($targetPath);

            throw new BackupFailedException(
                "mysqldump failed for '{$databaseName}': ".trim($process->getErrorOutput())
            );
        }

        // mysqldump can exit 0 having written nothing useful (an unreachable
        // database, a permissions problem mid-stream). An empty dump that is
        // recorded as a success is worse than a failure, because it is only
        // discovered during a restore.
        if (! is_file($targetPath) || filesize($targetPath) === 0) {
            throw new BackupFailedException("mysqldump produced an empty file for '{$databaseName}'.");
        }
    }

    public function restore(string $databaseName, string $sourcePath, string $connectionName = 'tenant'): void
    {
        $connection = config("database.connections.{$connectionName}");

        $process = Process::fromShellCommandline(
            sprintf(
                '%s --host=%s --port=%s --user=%s %s < %s',
                escapeshellcmd(config('backup.mysql_path')),
                escapeshellarg((string) ($connection['host'] ?? '127.0.0.1')),
                escapeshellarg((string) ($connection['port'] ?? 3306)),
                escapeshellarg((string) ($connection['username'] ?? 'root')),
                escapeshellarg($databaseName),
                escapeshellarg($sourcePath),
            ),
            env: ['MYSQL_PWD' => (string) ($connection['password'] ?? '')],
        );

        $process->setTimeout(config('backup.timeout_seconds'));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new BackupFailedException(
                "mysql restore failed for '{$databaseName}': ".trim($process->getErrorOutput())
            );
        }
    }
}
