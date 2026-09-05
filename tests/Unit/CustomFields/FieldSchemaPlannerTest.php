<?php

namespace Tests\Unit\CustomFields;

use App\Domain\CustomFields\ColumnSpec;
use App\Domain\CustomFields\CustomFieldStates;
use App\Domain\CustomFields\FieldSchemaPlanner;
use App\Domain\CustomFields\HostCeilings;
use App\Domain\CustomFields\SchemaIntent;
use App\Domain\CustomFields\TableSnapshot;
use App\Domain\CustomFields\Types\TextType;
use Tests\TestCase;

/**
 * The planner, tested with no database at all.
 *
 * This is where every rule worth being sure about lives, and it is the only
 * part of the reconciler the fast gate can prove anything about: SQLite has no
 * InnoDB row-size ceiling, no 3072-byte index limit and no error 1170, so the
 * constraints these tests encode are invisible to any test that goes through
 * a connection.
 */
class FieldSchemaPlannerTest extends TestCase
{
    private FieldSchemaPlanner $planner;

    private TextType $text;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planner = new FieldSchemaPlanner;
        $this->text = new TextType;
    }

    /** One entry of the shape CompiledCatalogueInterface::desiredSchema() produces. */
    private function desired(int $num, bool $filterable = false, string $state = CustomFieldStates::PENDING): array
    {
        $spec = $this->text->columnSpec($filterable);

        return [
            'num' => $num,
            'column' => "cf_{$num}",
            'type' => 'text',
            'spec' => $spec,
            'wants_index' => $filterable && $spec->indexable,
            'index_name' => "cf_{$num}_idx",
            'state' => $state,
        ];
    }

    /** An `appointments`-shaped table: 21 columns, 5 secondary indexes. */
    private function snapshot(array $columns = [], array $indexes = [], int $bytes = 4000, int $versions = 0): TableSnapshot
    {
        return new TableSnapshot(
            columns: array_merge(array_fill_keys(
                ['id', 'title', 'starts_at', 'ends_at', 'all_day', 'appointment_type_id'],
                'varchar(36)',
            ), $columns),
            secondaryIndexes: array_merge(['idx_a', 'idx_b', 'idx_c', 'idx_d', 'idx_e'], $indexes),
            declaredRowBytes: $bytes,
            rowVersions: $versions,
        );
    }

    /**
     * Applies a plan to a snapshot, so the next plan can be taken against the
     * world the first one produced. This is what makes idempotence assertable
     * without a database.
     */
    private function afterApplying(array $intents, TableSnapshot $snapshot): TableSnapshot
    {
        $columns = $snapshot->columns;
        $indexes = $snapshot->secondaryIndexes;
        $bytes = $snapshot->declaredRowBytes;
        $versions = $snapshot->rowVersions;

        foreach ($intents as $intent) {
            match ($intent->kind) {
                SchemaIntent::ADD_COLUMN => [
                    $columns[$intent->column] = $intent->spec->columnType(),
                    $bytes += $intent->spec->declaredBytes(),
                    $versions++,
                ],
                SchemaIntent::ADD_INDEX => $indexes[] = $intent->indexName,
                SchemaIntent::DROP_INDEX => $indexes = array_values(array_diff($indexes, [$intent->indexName])),
                SchemaIntent::RETIRE_COLUMN => [
                    $columns[$intent->newName] = $columns[$intent->column],
                    $columns = array_diff_key($columns, [$intent->column => null]),
                ],
                default => null,
            };
        }

        return new TableSnapshot($columns, array_values($indexes), $bytes, $versions);
    }

    public function test_a_filterable_field_gets_a_varchar_and_an_index(): void
    {
        $intents = $this->planner->plan([$this->desired(1, filterable: true)], $this->snapshot(), new HostCeilings, '260904');

        $this->assertCount(2, $intents);
        $this->assertSame(SchemaIntent::ADD_COLUMN, $intents[0]->kind);
        $this->assertSame('varchar(190)', $intents[0]->spec->columnType());
        $this->assertSame('instant', $intents[0]->cost);
        $this->assertSame(SchemaIntent::ADD_INDEX, $intents[1]->kind);
        $this->assertSame('cf_1_idx', $intents[1]->indexName);
    }

    public function test_a_display_only_field_gets_text_and_no_index(): void
    {
        // The study's first pitfall: a field nobody filters on must not spend
        // row bytes. TEXT stores off-page and costs the row a pointer.
        $intents = $this->planner->plan([$this->desired(2, filterable: false)], $this->snapshot(), new HostCeilings, '260904');

        $this->assertCount(1, $intents);
        $this->assertSame('text', $intents[0]->spec->columnType());
        $this->assertSame(12, $intents[0]->spec->declaredBytes());
        $this->assertLessThan(
            $this->text->columnSpec(true)->declaredBytes(),
            $intents[0]->spec->declaredBytes(),
            'A display-only field must cost the row less than a filterable one.',
        );
    }

    public function test_reconciling_twice_emits_nothing_the_second_time(): void
    {
        // The single most valuable property in this feature. It is what makes
        // the reconciler safe to run by hand, safe to re-run after a failure,
        // and usable as the repair for a tenant whose schema drifted.
        $desired = [
            $this->desired(1, filterable: true),
            $this->desired(2, filterable: false),
            $this->desired(3, filterable: true),
        ];

        $snapshot = $this->snapshot();
        $first = $this->planner->plan($desired, $snapshot, new HostCeilings, '260904');

        $this->assertNotEmpty($first);

        $second = $this->planner->plan($desired, $this->afterApplying($first, $snapshot), new HostCeilings, '260904');

        $this->assertSame([], $second, 'The second run must have nothing to do.');
    }

    public function test_the_index_budget_refuses_rather_than_letting_innodb_do_it(): void
    {
        // InnoDB caps secondary indexes at 64 and `appointments` already
        // spends 5. Refusing at our own lower ceiling means the tenant reads a
        // message about custom fields instead of the platform's next migration
        // failing for this one customer.
        $ceilings = new HostCeilings(maxSecondaryIndexes: 7);
        $desired = [
            $this->desired(1, filterable: true),
            $this->desired(2, filterable: true),
            $this->desired(3, filterable: true),
        ];

        $intents = $this->planner->plan($desired, $this->snapshot(), $ceilings, '260904');

        $refusals = array_values(array_filter($intents, fn ($i) => $i->isRefusal()));

        $this->assertCount(1, $refusals, 'Two index slots were free, so exactly the third must be refused.');
        $this->assertSame(FieldSchemaPlanner::REASON_INDEX_BUDGET, $refusals[0]->reasonCode);
        $this->assertSame('cf_3', $refusals[0]->column);

        // The column itself is still added — only the index is refused. The
        // tenant keeps the field and loses the filter, which is the smaller
        // loss and the one they can act on.
        $added = array_filter($intents, fn ($i) => $i->kind === SchemaIntent::ADD_COLUMN);
        $this->assertCount(3, $added);
    }

    public function test_budgets_are_consumed_across_one_plan_not_checked_against_the_start(): void
    {
        // Forty fields in one save must not each pass a check against the
        // starting state and blow the ceiling on execution.
        $ceilings = new HostCeilings(maxColumns: 8); // snapshot has 6
        $desired = [$this->desired(1), $this->desired(2), $this->desired(3), $this->desired(4)];

        $intents = $this->planner->plan($desired, $this->snapshot(), $ceilings, '260904');

        $this->assertCount(2, array_filter($intents, fn ($i) => $i->kind === SchemaIntent::ADD_COLUMN));
        $refusals = array_values(array_filter($intents, fn ($i) => $i->isRefusal()));
        $this->assertCount(2, $refusals);
        $this->assertSame(FieldSchemaPlanner::REASON_COLUMN_BUDGET, $refusals[0]->reasonCode);
    }

    public function test_the_declared_row_byte_budget_refuses_before_mysql_would(): void
    {
        $ceilings = new HostCeilings(maxDeclaredRowBytes: 4000);
        // A filterable text column costs 190 * 4 + 2 = 762 declared bytes.
        $snapshot = $this->snapshot(bytes: 3500);

        $intents = $this->planner->plan([$this->desired(1, filterable: true)], $snapshot, $ceilings, '260904');

        $this->assertTrue($intents[0]->isRefusal());
        $this->assertSame(FieldSchemaPlanner::REASON_ROW_BYTES, $intents[0]->reasonCode);
        $this->assertSame(762, $intents[0]->reasonParams['needed']);
    }

    public function test_the_row_version_budget_refuses_before_instant_add_stops_being_instant(): void
    {
        // MySQL 8.0.29+ stops honouring ALGORITHM=INSTANT at 64 row versions
        // and only a full rebuild resets the counter. Nobody expects this one,
        // which is exactly why it is a named refusal rather than a surprise
        // table rebuild during someone's working day.
        $ceilings = new HostCeilings(maxRowVersions: 3);
        $snapshot = $this->snapshot(versions: 3);

        $intents = $this->planner->plan([$this->desired(1)], $snapshot, $ceilings, '260904');

        $this->assertTrue($intents[0]->isRefusal());
        $this->assertSame(FieldSchemaPlanner::REASON_ROW_VERSIONS, $intents[0]->reasonCode);
    }

    public function test_retiring_drops_the_index_then_renames_the_column_in_place(): void
    {
        $snapshot = $this->snapshot(['cf_9' => 'varchar(190)'], ['cf_9_idx']);

        $intents = $this->planner->plan(
            [$this->desired(9, filterable: true, state: CustomFieldStates::RETIRING)],
            $snapshot,
            new HostCeilings,
            '260904',
        );

        $this->assertCount(2, $intents);
        $this->assertSame(SchemaIntent::DROP_INDEX, $intents[0]->kind);
        $this->assertSame(SchemaIntent::RETIRE_COLUMN, $intents[1]->kind);
        $this->assertSame('cf_9_retired_260904', $intents[1]->newName);

        // INPLACE, not INSTANT. MySQL 8 lists renaming a column as Instant=No,
        // and this reconciler names its algorithm rather than letting MySQL
        // choose — so INSTANT here would fail every retire, every time.
        $this->assertSame('inplace', $intents[1]->cost);
    }

    public function test_nothing_is_ever_dropped(): void
    {
        // These columns hold tenant data. "Deleted a field by accident" must
        // be answerable with an UPDATE, not a restore.
        $snapshot = $this->snapshot(['cf_9' => 'varchar(190)'], ['cf_9_idx']);

        $intents = $this->planner->plan(
            [$this->desired(9, filterable: true, state: CustomFieldStates::RETIRING)],
            $snapshot,
            new HostCeilings,
            '260904',
        );

        foreach ($intents as $intent) {
            $this->assertNotSame('drop_column', $intent->kind);
        }
    }

    public function test_a_column_the_planner_did_not_name_is_never_touched(): void
    {
        // The prime directive. Blast radius reduced to one regex: a tampered
        // definition row cannot reach the SQL builder.
        $desired = $this->desired(1);
        $desired['column'] = 'starts_at';

        $intents = $this->planner->plan([$desired], $this->snapshot(), new HostCeilings, '260904');

        $this->assertCount(1, $intents);
        $this->assertTrue($intents[0]->isRefusal());
        $this->assertSame(FieldSchemaPlanner::REASON_ILLEGAL_COLUMN, $intents[0]->reasonCode);
    }

    public function test_changing_filterability_on_a_live_column_is_refused_by_name(): void
    {
        // VARCHAR <-> TEXT is ALGORITHM=COPY: minutes on a large table, and in
        // the narrowing direction either error 1406 under strict mode or
        // silent truncation without it. It gets its own reconciler mode with a
        // pre-flight row count. Until then it is refused, not attempted.
        $snapshot = $this->snapshot(['cf_1' => 'text']);

        $intents = $this->planner->plan([$this->desired(1, filterable: true)], $snapshot, new HostCeilings, '260904');

        $this->assertCount(1, $intents);
        $this->assertTrue($intents[0]->isRefusal());
        $this->assertSame(FieldSchemaPlanner::REASON_TYPE_CHANGE, $intents[0]->reasonCode);
        $this->assertSame('text', $intents[0]->reasonParams['from']);
        $this->assertSame('varchar(190)', $intents[0]->reasonParams['to']);
        $this->assertSame('rebuild', $intents[0]->cost);
    }

    public function test_an_index_the_tenant_no_longer_wants_is_dropped(): void
    {
        // Index budget is the binding resource, so an index nobody filters on
        // is pure cost and is returned to the pool.
        $snapshot = $this->snapshot(['cf_1' => 'varchar(190)'], ['cf_1_idx']);

        $intents = $this->planner->plan([
            // Same column type, so no rewrite: the tenant turned the filter
            // off on a field that was created filterable.
            array_merge($this->desired(1, filterable: true), ['wants_index' => false]),
        ], $snapshot, new HostCeilings, '260904');

        $this->assertCount(1, $intents);
        $this->assertSame(SchemaIntent::DROP_INDEX, $intents[0]->kind);
    }
}
