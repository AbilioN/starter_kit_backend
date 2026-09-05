<?php

namespace App\Infrastructure\Services;

use App\Domain\CustomFields\TableSnapshot;
use App\Domain\Services\SchemaIntrospectorInterface;
use Illuminate\Support\Facades\DB;

/**
 * Reads a tenant table's real shape out of information_schema.
 *
 * Every query is scoped to the CURRENT connection's database. That is not
 * defensive style: all tenants share one MySQL server, so an unscoped
 * information_schema query would happily report another tenant's `cf_*`
 * columns as if they were this one's — and the reconciler would then decide
 * this tenant's schema was already correct.
 */
class MySqlSchemaIntrospector implements SchemaIntrospectorInterface
{
    public function assertUsable(): void
    {
        $driver = DB::connection('tenant')->getDriverName();

        if ($driver !== 'mysql') {
            throw new \RuntimeException(
                "Schema introspection for custom fields requires MySQL; this connection is [{$driver}]. "
                .'See docs/05-running-the-tests.md — the reconciler is MySQL-only on purpose.'
            );
        }
    }

    public function snapshot(string $table): TableSnapshot
    {
        $this->assertUsable();

        $database = $this->database();

        $columns = [];
        $bytes = 0;

        foreach ($this->columnRows($database, $table) as $row) {
            $row = $this->normalise($row);
            $columns[$row['column_name']] = strtolower((string) $row['column_type']);
            $bytes += $this->declaredBytes($row);
        }

        return new TableSnapshot(
            columns: $columns,
            secondaryIndexes: $this->secondaryIndexes($database, $table),
            declaredRowBytes: $bytes,
            rowVersions: $this->rowVersions($database, $table),
        );
    }

    public function unclaimedFieldColumns(string $table, array $claimedColumns): array
    {
        $claimed = array_flip($claimedColumns);

        return array_values(array_filter(
            array_keys($this->snapshot($table)->columns),
            fn (string $column) => preg_match('/^cf_\d+$/', $column) === 1
                && ! isset($claimed[$column]),
        ));
    }

    /**
     * information_schema answers with UPPERCASE column names on MySQL 8
     * regardless of how the select was written — its columns are defined that
     * way in the data dictionary. Normalising here rather than spelling the
     * case out at each call site keeps this from being rediscovered by
     * whoever adds the next query.
     *
     * @return array<string, mixed>
     */
    private function normalise(object|array $row): array
    {
        return array_change_key_case((array) $row, CASE_LOWER);
    }

    private function database(): string
    {
        return (string) DB::connection('tenant')->getDatabaseName();
    }

    private function columnRows(string $database, string $table): array
    {
        return DB::connection('tenant')->select(
            'select column_name, column_type, data_type, character_octet_length, numeric_precision
               from information_schema.columns
              where table_schema = ? and table_name = ?
              order by ordinal_position',
            [$database, $table],
        );
    }

    /** @return array<int, string> */
    private function secondaryIndexes(string $database, string $table): array
    {
        $rows = DB::connection('tenant')->select(
            'select distinct index_name
               from information_schema.statistics
              where table_schema = ? and table_name = ? and index_name <> ?',
            [$database, $table, 'PRIMARY'],
        );

        return array_map(fn ($row) => (string) $this->normalise($row)['index_name'], $rows);
    }

    /**
     * How many times this table has been changed by an instant ADD.
     *
     * TOTAL_ROW_VERSIONS, not INSTANT_COLS. INSTANT_COLS is the column count
     * PRIOR to the first instant ADD and reads 0 on every untouched table, so
     * a ceiling built on it would never fire. TOTAL_ROW_VERSIONS is the 0..64
     * counter MySQL 8.0.29+ actually refuses ALGORITHM=INSTANT on, and only a
     * full table rebuild resets it.
     *
     * Returns 0 when the view is unavailable (an older server, or a user
     * without PROCESS). A missing counter must not block reconciliation — it
     * only means this particular ceiling cannot be enforced, and MySQL's own
     * error would then be the backstop.
     */
    private function rowVersions(string $database, string $table): int
    {
        try {
            $row = DB::connection('tenant')->selectOne(
                'select total_row_versions from information_schema.innodb_tables where name = ?',
                [$database.'/'.$table],
            );

            return $row ? (int) $this->normalise($row)['total_row_versions'] : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * The declared byte cost of one column against MySQL's 65,535-byte row
     * limit — the limit that counts DEFINITIONS, not stored data.
     *
     * Mirrors ColumnSpec::declaredBytes() so the planner's arithmetic and the
     * live measurement agree. Deliberately approximate and deliberately
     * generous: the number only has to be safely below the engine's, and the
     * product ceiling sits well under it anyway.
     */
    private function declaredBytes(array $row): int
    {
        return match (strtolower((string) $row['data_type'])) {
            'varchar', 'char' => (int) ($row['character_octet_length'] ?? 0) + 2,
            'text', 'mediumtext', 'longtext', 'tinytext',
            'blob', 'mediumblob', 'longblob', 'tinyblob', 'json' => 12,
            'tinyint' => 1,
            'smallint' => 2,
            'mediumint' => 3,
            'int', 'integer' => 4,
            'bigint' => 8,
            'date' => 3,
            'datetime', 'timestamp' => 8,
            'decimal', 'numeric' => (int) ceil((((int) ($row['numeric_precision'] ?? 10)) + 2) / 2),
            default => 8,
        };
    }
}
