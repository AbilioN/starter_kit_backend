<?php

namespace App\Infrastructure\Services;

use App\Domain\Exceptions\BackupFailedException;
use App\Domain\Services\BackupArchiverInterface;
use Symfony\Component\Process\Process;

/**
 * gzip, then `openssl enc -aes-256-cbc -pbkdf2`.
 *
 * Shelled out rather than done in PHP on purpose: both tools stream, and a dump
 * is the one file in this application whose size is bounded by the customer
 * rather than by us. Reading it into memory to call openssl_encrypt() would
 * take the container down on exactly the tenants whose backup matters most.
 *
 * The key is passed through the environment, never on the command line —
 * argv is world-readable via /proc.
 */
class BackupArchiver implements BackupArchiverInterface
{
    private const KEY_ENV = 'BACKUP_ARCHIVE_KEY';

    private const MISSING_KEY_MESSAGE = 'Backup encryption is enabled but BACKUP_ENCRYPTION_KEY is not set. '
        .'Set a key distinct from APP_KEY, or set BACKUP_ENCRYPTION_ENABLED=false deliberately.';

    public function extension(string $base = '.sql'): string
    {
        // Deliberately does NOT validate the key, and must not start doing so:
        // callers use this to NAME a file, which typically happens before their
        // own failure handling is in place. It used to go through
        // isEncryptionEnabled(), so a missing key threw from here — two lines
        // above the try/catch that marks a backup failed — and every run
        // between 2026-08-21 and 2026-08-28 left its ledger row stuck on
        // `running` with no reason recorded. Whether the key exists is
        // archive()'s question, and assertUsable()'s.
        return config('backup.encryption.enabled') ? $base.'.gz.enc' : $base.'.gz';
    }

    public function assertUsable(): void
    {
        if (config('backup.encryption.enabled') && blank(config('backup.encryption.key'))) {
            throw new BackupFailedException(self::MISSING_KEY_MESSAGE);
        }
    }

    public function archive(string $sourcePath, string $targetPath): array
    {
        $encrypt = $this->isEncryptionEnabled();

        $command = $encrypt
            ? sprintf(
                'gzip -c %s | openssl enc -%s -pbkdf2 -salt -pass env:%s -out %s',
                escapeshellarg($sourcePath),
                escapeshellcmd(config('backup.encryption.cipher')),
                self::KEY_ENV,
                escapeshellarg($targetPath),
            )
            : sprintf('gzip -c %s > %s', escapeshellarg($sourcePath), escapeshellarg($targetPath));

        $this->run($command, $encrypt, "Failed to archive '{$sourcePath}'");

        if (! is_file($targetPath) || filesize($targetPath) === 0) {
            throw new BackupFailedException("Archiving produced an empty file for '{$sourcePath}'.");
        }

        return ['path' => $targetPath, 'encrypted' => $encrypt];
    }

    public function extract(string $sourcePath, string $targetPath, bool $encrypted): void
    {
        // Driven by the ledger row's own is_encrypted flag, not by the current
        // config: encryption may have been turned on after this backup was
        // written, and a restore has to read the file as it actually is.
        $command = $encrypted
            ? sprintf(
                'openssl enc -d -%s -pbkdf2 -pass env:%s -in %s | gunzip -c > %s',
                escapeshellcmd(config('backup.encryption.cipher')),
                self::KEY_ENV,
                escapeshellarg($sourcePath),
                escapeshellarg($targetPath),
            )
            : sprintf('gunzip -c %s > %s', escapeshellarg($sourcePath), escapeshellarg($targetPath));

        $this->run($command, $encrypted, "Failed to extract '{$sourcePath}'");
    }

    private function isEncryptionEnabled(): bool
    {
        if (! config('backup.encryption.enabled')) {
            return false;
        }

        // Still throws — but only from archive()/extract(), which their callers
        // run inside the try that marks the ledger row failed.
        $this->assertUsable();

        return true;
    }

    private function run(string $command, bool $withKey, string $context): void
    {
        // bash with pipefail, not Process::fromShellCommandline's /bin/sh: in a
        // plain shell a pipeline reports only the LAST command's exit status,
        // so `gzip -c missing | openssl ... -out dump.enc` exits 0 and hands
        // back a perfectly well-formed encrypted file containing nothing.
        $process = new Process(
            ['bash', '-o', 'pipefail', '-c', $command],
            env: $withKey ? [self::KEY_ENV => (string) config('backup.encryption.key')] : [],
        );

        $process->setTimeout(config('backup.timeout_seconds'));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new BackupFailedException($context.': '.trim($process->getErrorOutput()));
        }
    }
}
