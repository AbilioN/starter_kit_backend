<?php

namespace App\Domain\CustomFields;

/**
 * How much room a host table has left, and how much of it this product is
 * willing to spend.
 *
 * Every number here is deliberately BELOW the engine's own, for one reason:
 * if a tenant is allowed to spend the last index slot or the last row byte,
 * the next thing that fails is a core migration — and it fails for that
 * tenant only, at deploy time, with an error that names MySQL rather than
 * custom fields. Refusing the tenant's 49th filterable field is a message
 * somebody can act on; refusing the platform's next migration is not.
 *
 * Verified against the live stack on 2026-09-04 (MySQL 8.0.46):
 * `appointments` 21 columns / 5 secondary indexes, `users` 11 / 1, both
 * ROW_FORMAT=Dynamic InnoDB.
 */
final class HostCeilings
{
    public function __construct(
        /**
         * InnoDB allows 64 secondary indexes per table. This is the binding
         * constraint for this feature — not the row-size limit the study
         * warns about — because "filterable" means "indexed", and
         * `appointments` already spends 5 of them.
         */
        public readonly int $maxSecondaryIndexes = 48,

        /**
         * MySQL's DECLARED row limit is 65,535 bytes, counted across the
         * column definitions rather than the stored data. One utf8mb4
         * VARCHAR(255) costs 1,022 of them.
         */
        public readonly int $maxDeclaredRowBytes = 40000,

        /** InnoDB's hard cap is 1017 columns. */
        public readonly int $maxColumns = 200,

        /**
         * MySQL 8.0.29+ refuses ALGORITHM=INSTANT once a table has
         * accumulated 64 row versions, and the only way to reset the counter
         * is a full table rebuild. Read from
         * information_schema.INNODB_TABLES.TOTAL_ROW_VERSIONS — NOT from
         * INSTANT_COLS, which is the column count prior to the first instant
         * ADD and reads 0 on every untouched table, i.e. a ceiling that would
         * never fire.
         */
        public readonly int $maxRowVersions = 48,
    ) {}
}
