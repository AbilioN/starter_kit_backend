<?php

namespace App\Domain\CustomFields;

/**
 * Decides what has to change to make a host table match a tenant's field
 * definitions — and refuses, by name, when it must not.
 *
 * Pure. No database, no container, no config, no driver. That is not a purity
 * exercise: this class is where every rule worth being sure about lives (the
 * ceilings, the never-drop rule, the column-name allowlist, idempotence), and
 * the default test suite runs on SQLite, where none of the constraints those
 * rules encode exist. Keeping the decisions here means they can be tested
 * exhaustively on the fast gate, and only the SQL needs MySQL.
 *
 * ## The prime directive
 *
 * This planner only ever names columns matching /^cf_\d+$/, and only ever
 * renames one to /^cf_\d+_retired_\d{6}$/. A column it did not name is not
 * its business.
 *
 * That single rule is the entire blast radius of runtime DDL in this product.
 * It makes "the reconciler dropped appointments.starts_at" structurally
 * impossible rather than carefully avoided, and it makes drift detection
 * trivially correct: an unexpected cf_* column is drift, and every other
 * column is invisible.
 *
 * ## What it will never emit
 *
 * No DROP COLUMN, and no narrowing. These columns hold tenant data. Dropping
 * is irreversible and is a table rebuild besides, and "a tenant deleted a
 * field by accident" should be answerable with an UPDATE rather than a
 * restore — the same instinct as the backup rule that restores into a new
 * database rather than over the live one. A real DROP COLUMN is an operator
 * command with --confirm and --actor.
 */
final class FieldSchemaPlanner
{
    public const REASON_INDEX_BUDGET = 'index_budget_exhausted';
    public const REASON_COLUMN_BUDGET = 'column_budget_exhausted';
    public const REASON_ROW_BYTES = 'row_byte_budget_exhausted';
    public const REASON_ROW_VERSIONS = 'row_version_budget_exhausted';
    public const REASON_TYPE_CHANGE = 'type_change_not_supported_yet';
    public const REASON_ILLEGAL_COLUMN = 'illegal_column_name';

    /**
     * @param  array<int, array<string, mixed>>  $desiredSchema  from CompiledCatalogueInterface::desiredSchema()
     * @param  string  $retiredSuffix  the YYMMDD stamp for names parked by this run,
     *         passed in rather than read from the clock so a plan is reproducible
     *         and a test can assert the exact name
     * @return array<int, SchemaIntent>
     */
    public function plan(
        array $desiredSchema,
        TableSnapshot $snapshot,
        HostCeilings $ceilings,
        string $retiredSuffix,
    ): array {
        $intents = [];

        // Budgets are consumed as the plan is built, not checked against the
        // starting state. Otherwise a single save adding forty filterable
        // fields would pass forty individual checks and blow the ceiling once
        // executed — the ceiling would be a report rather than a limit.
        $indexBudget = $ceilings->maxSecondaryIndexes - $snapshot->secondaryIndexCount();
        $columnBudget = $ceilings->maxColumns - $snapshot->columnCount();
        $byteBudget = $ceilings->maxDeclaredRowBytes - $snapshot->declaredRowBytes;
        $versionBudget = $ceilings->maxRowVersions - $snapshot->rowVersions;

        foreach ($desiredSchema as $desired) {
            $column = (string) $desired['column'];
            $num = (int) $desired['num'];

            // The allowlist, enforced before anything else looks at the name.
            // A definition row whose column_name was tampered with must not
            // reach the SQL builder at all.
            if (preg_match('/^cf_\d+$/', $column) !== 1) {
                $intents[] = new SchemaIntent(
                    kind: SchemaIntent::REFUSE,
                    column: $column,
                    num: $num,
                    reasonCode: self::REASON_ILLEGAL_COLUMN,
                    reasonParams: ['column' => $column],
                );

                continue;
            }

            if ($desired['state'] === CustomFieldStates::RETIRING) {
                $intents = array_merge($intents, $this->planRetire($desired, $snapshot, $retiredSuffix));

                // Retiring returns an index slot rather than spending one.
                if ($snapshot->hasIndex((string) $desired['index_name'])) {
                    $indexBudget++;
                }

                continue;
            }

            /** @var ColumnSpec $spec */
            $spec = $desired['spec'];

            if (! $snapshot->hasColumn($column)) {
                $refusal = $this->refuseIfNoRoom($desired, $columnBudget, $byteBudget, $versionBudget, $spec, $ceilings);

                if ($refusal !== null) {
                    $intents[] = $refusal;

                    continue;
                }

                $columnBudget--;
                $versionBudget--;
                $byteBudget -= $spec->declaredBytes();

                $intents[] = new SchemaIntent(
                    kind: SchemaIntent::ADD_COLUMN,
                    column: $column,
                    num: $num,
                    spec: $spec,
                    cost: 'instant',
                );
            } elseif (! $this->typeMatches($snapshot->columnType($column), $spec)) {
                // The column exists with a different shape. That happens when
                // a tenant toggles filterability, which is a VARCHAR<->TEXT
                // rewrite: ALGORITHM=COPY, minutes on a large table, and in
                // the narrowing direction either error 1406 under strict mode
                // or silent truncation without it. It gets its own reconciler
                // mode, with a pre-flight row count that refuses with the
                // number of rows that would be lost. Until then it is refused
                // by name rather than attempted.
                $intents[] = new SchemaIntent(
                    kind: SchemaIntent::REFUSE,
                    column: $column,
                    num: $num,
                    spec: $spec,
                    reasonCode: self::REASON_TYPE_CHANGE,
                    reasonParams: [
                        'from' => $snapshot->columnType($column),
                        'to' => $spec->columnType(),
                    ],
                    cost: 'rebuild',
                );

                continue;
            }

            $indexIntent = $this->planIndex($desired, $snapshot, $indexBudget);

            if ($indexIntent !== null) {
                if ($indexIntent->kind === SchemaIntent::ADD_INDEX) {
                    $indexBudget--;
                } elseif ($indexIntent->kind === SchemaIntent::DROP_INDEX) {
                    $indexBudget++;
                }

                $intents[] = $indexIntent;
            }
        }

        return $intents;
    }

    /** @return array<int, SchemaIntent> */
    private function planRetire(array $desired, TableSnapshot $snapshot, string $retiredSuffix): array
    {
        $column = (string) $desired['column'];
        $intents = [];

        // Index first. Dropping it before the rename means the rename does not
        // have to carry the index with it, and an index on a column nobody
        // reads is pure cost against the budget that actually binds.
        if ($snapshot->hasIndex((string) $desired['index_name'])) {
            $intents[] = new SchemaIntent(
                kind: SchemaIntent::DROP_INDEX,
                column: $column,
                num: (int) $desired['num'],
                indexName: (string) $desired['index_name'],
                cost: 'inplace',
            );
        }

        if ($snapshot->hasColumn($column)) {
            $intents[] = new SchemaIntent(
                kind: SchemaIntent::RETIRE_COLUMN,
                column: $column,
                num: (int) $desired['num'],
                newName: $column.'_retired_'.$retiredSuffix,
                // Not INSTANT. MySQL 8 lists renaming a column as In Place
                // only, and this reconciler names its algorithm rather than
                // letting MySQL pick — so naming the wrong one is a guaranteed
                // failure, not a silent downgrade.
                cost: 'inplace',
            );
        }

        return $intents;
    }

    private function planIndex(array $desired, TableSnapshot $snapshot, int $indexBudget): ?SchemaIntent
    {
        $indexName = (string) $desired['index_name'];
        $wantsIndex = (bool) $desired['wants_index'];
        $hasIndex = $snapshot->hasIndex($indexName);

        if ($wantsIndex && ! $hasIndex) {
            if ($indexBudget <= 0) {
                return new SchemaIntent(
                    kind: SchemaIntent::REFUSE,
                    column: (string) $desired['column'],
                    num: (int) $desired['num'],
                    indexName: $indexName,
                    reasonCode: self::REASON_INDEX_BUDGET,
                    reasonParams: ['remaining' => 0],
                );
            }

            return new SchemaIntent(
                kind: SchemaIntent::ADD_INDEX,
                column: (string) $desired['column'],
                num: (int) $desired['num'],
                indexName: $indexName,
                cost: 'inplace',
            );
        }

        if (! $wantsIndex && $hasIndex) {
            return new SchemaIntent(
                kind: SchemaIntent::DROP_INDEX,
                column: (string) $desired['column'],
                num: (int) $desired['num'],
                indexName: $indexName,
                cost: 'inplace',
            );
        }

        return null;
    }

    private function refuseIfNoRoom(
        array $desired,
        int $columnBudget,
        int $byteBudget,
        int $versionBudget,
        ColumnSpec $spec,
        HostCeilings $ceilings,
    ): ?SchemaIntent {
        $refuse = fn (string $code, array $params) => new SchemaIntent(
            kind: SchemaIntent::REFUSE,
            column: (string) $desired['column'],
            num: (int) $desired['num'],
            spec: $spec,
            reasonCode: $code,
            reasonParams: $params,
        );

        if ($columnBudget <= 0) {
            return $refuse(self::REASON_COLUMN_BUDGET, ['limit' => $ceilings->maxColumns]);
        }

        // The one nobody expects. MySQL 8.0.29+ stops honouring
        // ALGORITHM=INSTANT once a table has accumulated 64 row versions, and
        // only a full rebuild resets the counter. Refusing at 48 means the
        // failure is a message about custom fields rather than a mysterious
        // ALTER that suddenly locks the agenda for minutes.
        if ($versionBudget <= 0) {
            return $refuse(self::REASON_ROW_VERSIONS, ['limit' => $ceilings->maxRowVersions]);
        }

        if ($byteBudget - $spec->declaredBytes() < 0) {
            return $refuse(self::REASON_ROW_BYTES, [
                'limit' => $ceilings->maxDeclaredRowBytes,
                'needed' => $spec->declaredBytes(),
                'remaining' => max(0, $byteBudget),
            ]);
        }

        return null;
    }

    /**
     * Whether the live column is already what the definition asks for.
     *
     * Compared case-insensitively against what information_schema reports
     * ("varchar(190)", "text", "decimal(14,4)"). Under SQLite the same read
     * returns "varchar" with the length discarded, which is exactly why the
     * reconcile-twice-emits-nothing property can only be asserted on MySQL —
     * and why the fast gate asserts the PLAN instead of the SQL.
     */
    private function typeMatches(?string $actual, ColumnSpec $spec): bool
    {
        if ($actual === null) {
            return false;
        }

        return strtolower(trim($actual)) === strtolower($spec->columnType());
    }
}
