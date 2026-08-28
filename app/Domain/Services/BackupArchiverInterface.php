<?php

namespace App\Domain\Services;

/**
 * Compresses and encrypts a dump on its way out, and reverses that on the way
 * back in.
 *
 * Encryption is not optional decoration here: a dump is every row of a
 * customer's database in one file, and the whole point of a backup is that the
 * file is copied somewhere else — frequently to a bucket this team does not own.
 */
interface BackupArchiverInterface
{
    /**
     * @return array{path: string, encrypted: bool} the archived file
     *
     * @throws \App\Domain\Exceptions\BackupFailedException
     */
    public function archive(string $sourcePath, string $targetPath): array;

    /**
     * @throws \App\Domain\Exceptions\BackupFailedException
     */
    public function extract(string $sourcePath, string $targetPath, bool $encrypted): void;

    /**
     * Suffix for an archived file, so the extension says what the file is
     * without having to open it. `$base` is the payload's own extension
     * ('.sql' for a dump, '.tar' for a file bundle).
     *
     * Must never throw: it is called to name a file, often before the caller
     * has a failure path to record the error on.
     */
    public function extension(string $base = '.sql'): string;

    /**
     * Fails when the archiver is configured in a way it cannot honour —
     * encryption switched on with no key, today.
     *
     * Exists so a run can be refused up front, once, with one legible message,
     * instead of once per tenant halfway through a nightly sweep.
     *
     * @throws \App\Domain\Exceptions\BackupFailedException
     */
    public function assertUsable(): void;
}
