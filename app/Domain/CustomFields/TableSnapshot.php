<?php

namespace App\Domain\CustomFields;

/**
 * What a host table actually looks like right now.
 *
 * Read from information_schema, never assumed from the migrations. Tenant
 * schemas already drift in this product — a migration reaches only the
 * databases that existed when someone last ran `tenant:migrate` — so a
 * reconciler that diffed against an assumed baseline would ALTER a table
 * whose shape it did not predict. Diffing against reality is also what makes
 * the whole thing idempotent, and therefore what lets it double as the repair
 * for a tenant whose schema drifted.
 */
final class TableSnapshot
{
    /**
     * @param  array<string, string>  $columns  column name => column_type as
     *         MySQL reports it ("varchar(190)", "text")
     * @param  array<int, string>  $secondaryIndexes  index names, PRIMARY excluded
     * @param  int  $declaredRowBytes  the table's current declared row size
     * @param  int  $rowVersions  information_schema.INNODB_TABLES.TOTAL_ROW_VERSIONS
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $secondaryIndexes,
        public readonly int $declaredRowBytes,
        public readonly int $rowVersions,
    ) {}

    public function hasColumn(string $column): bool
    {
        return array_key_exists($column, $this->columns);
    }

    public function columnType(string $column): ?string
    {
        return $this->columns[$column] ?? null;
    }

    public function hasIndex(string $index): bool
    {
        return in_array($index, $this->secondaryIndexes, true);
    }

    public function columnCount(): int
    {
        return count($this->columns);
    }

    public function secondaryIndexCount(): int
    {
        return count($this->secondaryIndexes);
    }
}
