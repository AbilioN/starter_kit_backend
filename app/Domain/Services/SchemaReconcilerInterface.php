<?php

namespace App\Domain\Services;

use App\Domain\CustomFields\SchemaIntent;

/**
 * Turns intents into DDL.
 *
 * MySQL only, and deliberately so. A SQLite implementation would be a code
 * path production never executes, written purely so the fast suite could feel
 * covered — precisely the fake confidence docs/05-running-the-tests.md and
 * DatabaseDumperInterface's docblock exist to prevent. Under SQLite there is
 * no MODIFY COLUMN, `->change()` silently rebuilds the whole table, and
 * `Schema::getColumns()['type']` discards the length, so a reconciler tested
 * there would be idempotent for reasons that have nothing to do with being
 * correct.
 *
 * Feature tests fake this interface the way RunBackupTest fakes the mysqldump
 * wrapper — and the fake records the intents it was handed, so the fast gate
 * still asserts the PLAN. It just never asserts the SQL.
 */
interface SchemaReconcilerInterface
{
    /**
     * Refuse, once and clearly, if this cannot run here.
     *
     * Asked once up front rather than per statement: BackupArchiver's lesson
     * is that a nightly sweep should fail with one message instead of N.
     *
     * @throws \RuntimeException
     */
    public function assertUsable(): void;

    /**
     * Apply the intents in order, returning what actually ran.
     *
     * The return value is appended to the ledger statement by statement, so a
     * process killed mid-run leaves behind the difference between what was
     * planned and what was done.
     *
     * @param  array<int, SchemaIntent>  $intents
     * @return array<int, array<string, mixed>>
     */
    public function apply(string $table, array $intents): array;
}
