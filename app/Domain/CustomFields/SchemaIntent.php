<?php

namespace App\Domain\CustomFields;

/**
 * One thing the reconciler intends to do to a host table.
 *
 * Intents are produced by a pure planner and consumed by a MySQL-only
 * executor. The split is the same one DatabaseDumperInterface /
 * MysqlDatabaseDumper already makes, and for the same reason: the decisions
 * worth testing exhaustively (ceilings, idempotence, the never-drop rule) have
 * nothing to do with SQL, and testing them through SQL would mean testing them
 * on SQLite, where none of the constraints they encode exist.
 *
 * They are also written to the ledger BEFORE anything runs, which is what
 * makes a process killed mid-ALTER legible afterwards: the plan says what was
 * meant, `applied` says how far it got.
 */
final class SchemaIntent
{
    /** ADD COLUMN. ALGORITHM=INSTANT on MySQL 8. */
    public const ADD_COLUMN = 'add_column';

    /** ADD INDEX. INPLACE, LOCK=NONE. */
    public const ADD_INDEX = 'add_index';

    /** DROP INDEX. INPLACE, LOCK=NONE. Index budget is the scarce resource. */
    public const DROP_INDEX = 'drop_index';

    /**
     * RENAME COLUMN to cf_N_retired_YYMMDD.
     *
     * INPLACE, not INSTANT: MySQL 8's online-DDL matrix lists renaming a
     * column as Instant=No, In Place=Yes. Naming INSTANT here would make
     * every retire fail, every time.
     */
    public const RETIRE_COLUMN = 'retire_column';

    /**
     * Nothing will be done, and the definition is marked failed with this
     * reason. A refusal is a first-class outcome, not an exception: a tenant
     * who hits a ceiling must read what they hit, on the screen they are
     * already looking at.
     */
    public const REFUSE = 'refuse';

    public function __construct(
        public readonly string $kind,
        public readonly string $column,
        public readonly ?int $num = null,
        /** Present for add_column: the column definition to create. */
        public readonly ?ColumnSpec $spec = null,
        /** Present for index work. */
        public readonly ?string $indexName = null,
        /** Present for retire_column: the parked name. */
        public readonly ?string $newName = null,
        /** A translatable code, never a frozen-language sentence. */
        public readonly ?string $reasonCode = null,
        /** @var array<string, mixed> */
        public readonly array $reasonParams = [],
        /** instant | inplace | rebuild — what this costs the table. */
        public readonly string $cost = 'instant',
    ) {}

    public function isRefusal(): bool
    {
        return $this->kind === self::REFUSE;
    }

    /** @return array<string, mixed> for the ledger's `intents` column. */
    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind,
            'column' => $this->column,
            'num' => $this->num,
            'column_type' => $this->spec?->columnType(),
            'index_name' => $this->indexName,
            'new_name' => $this->newName,
            'reason_code' => $this->reasonCode,
            'reason_params' => $this->reasonParams ?: null,
            'cost' => $this->cost,
        ], fn ($v) => $v !== null);
    }
}
