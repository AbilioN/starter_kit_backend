<?php

namespace Tests\Concerns;

/**
 * Marks a test as one that only means anything on the engine production runs.
 *
 * The suite has had the opposite of this since the beginning —
 * tests/Unit/Spike/DualConnectionSpikeTest.php skips ON MySQL, because it
 * relies on transactional DDL — but nothing that *requires* MySQL, which is
 * why the class of bug docs/05-running-the-tests.md warns about kept getting
 * through. Under SQLite there is no InnoDB row-size ceiling, no 3072-byte
 * index limit, no error 1170 for an index on a TEXT column, no strict-mode
 * 1406 on an over-long write, DECIMAL collapses to a float, and
 * `Schema::getColumns()['type']` discards the length — so a reconciler test
 * would pass or fail there for reasons that have nothing to do with whether
 * the reconciler is correct.
 *
 * Pair it with #[Group('mysql-ddl')] so the slow, database-creating tests can
 * be run on their own:
 *
 *     vendor/bin/phpunit -c phpunit.mysql.xml --group=mysql-ddl
 */
trait RequiresMySql
{
    protected function skipUnlessMySql(): void
    {
        if ($this->currentDriver() !== 'mysql') {
            $this->markTestSkipped('Requires MySQL; run with phpunit.mysql.xml.');
        }
    }

    protected function currentDriver(): ?string
    {
        return config('database.connections.'.config('database.default').'.driver');
    }
}
