<?php

namespace Tests\Feature\CustomFields;

use App\Domain\CustomFields\CustomFieldStates;
use App\Domain\CustomFields\FieldSchemaPlanner;
use App\Domain\CustomFields\HostCeilings;
use App\Domain\CustomFields\SchemaIntent;
use App\Domain\CustomFields\Types\TextType;
use App\Infrastructure\Services\MySqlSchemaIntrospector;
use App\Infrastructure\Services\MySqlSchemaReconciler;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\DdlTestCase;

/**
 * The things only MySQL can answer.
 *
 * Everything else about custom fields is asserted on the fast gate — the plan,
 * the ceilings, idempotence, the role rules, the projection. What lives here is
 * the short list SQLite is structurally unable to tell the truth about: the
 * exact stored column type, error 1170 for an index on TEXT, error 1406 under
 * strict mode, and whether an ALTER with a named ALGORITHM is one MySQL will
 * actually accept.
 *
 * Each test runs in its own throwaway database (see DdlTestCase) because MySQL
 * commits implicitly on DDL, so there is no transaction to roll any of this
 * back.
 */
#[Group('mysql-ddl')]
class SchemaReconcilerMySqlTest extends DdlTestCase
{
    private MySqlSchemaReconciler $reconciler;

    private MySqlSchemaIntrospector $introspector;

    private FieldSchemaPlanner $planner;

    private TextType $text;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reconciler = new MySqlSchemaReconciler;
        $this->introspector = new MySqlSchemaIntrospector;
        $this->planner = new FieldSchemaPlanner;
        $this->text = new TextType;
    }

    private function desired(int $num, bool $filterable, string $state = CustomFieldStates::PENDING): array
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

    private function reconcile(array $desired): array
    {
        $intents = $this->planner->plan(
            $desired,
            $this->introspector->snapshot('appointments'),
            new HostCeilings,
            '260904',
        );

        $this->reconciler->apply('appointments', $intents);

        return $intents;
    }

    public function test_a_filterable_field_becomes_exactly_varchar_190_with_an_index(): void
    {
        $this->reconcile([$this->desired(1, filterable: true)]);

        $column = $this->columnDefinition('appointments', 'cf_1');

        // The assertion SQLite cannot make: SQLiteGrammar::typeString discards
        // the length, so `getColumns()['type']` reads "varchar" there whatever
        // width was asked for.
        $this->assertSame('varchar(190)', $column['column_type']);
        $this->assertSame('YES', $column['is_nullable']);
        $this->assertSame(190, (int) $column['character_maximum_length']);

        $this->assertContains('cf_1_idx', $this->indexNames('appointments'));
    }

    public function test_a_display_only_field_becomes_text_with_no_index(): void
    {
        $this->reconcile([$this->desired(2, filterable: false)]);

        $this->assertSame('text', $this->columnDefinition('appointments', 'cf_2')['column_type']);
        $this->assertNotContains('cf_2_idx', $this->indexNames('appointments'));
    }

    public function test_reconciling_twice_emits_nothing_the_second_time(): void
    {
        $desired = [
            $this->desired(1, filterable: true),
            $this->desired(2, filterable: false),
        ];

        $first = $this->reconcile($desired);
        $this->assertNotEmpty($first);

        // Planned against the REAL post-ALTER schema read back out of
        // information_schema — which is the version of this property that
        // means something. The fast gate can only assert it against a
        // simulated snapshot.
        $second = $this->planner->plan(
            $desired,
            $this->introspector->snapshot('appointments'),
            new HostCeilings,
            '260904',
        );

        $this->assertSame([], $second);
    }

    public function test_an_index_on_a_text_column_is_error_1170_and_the_planner_never_asks_for_one(): void
    {
        $this->reconcile([$this->desired(3, filterable: false)]);

        // The engine's answer, stated so the constraint is written down where
        // someone changing the planner will find it.
        try {
            DB::connection('tenant')->statement('ALTER TABLE `appointments` ADD INDEX `cf_3_idx` (`cf_3`)');
            $this->fail('MySQL should refuse an index on a TEXT column without a prefix length.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('1170', $e->getMessage());
        }

        // And the planner's answer: it never proposes one, because
        // ColumnSpec::indexable is false for TEXT.
        $intents = $this->planner->plan(
            [$this->desired(3, filterable: false)],
            $this->introspector->snapshot('appointments'),
            new HostCeilings,
            '260904',
        );

        $this->assertSame([], $intents);
    }

    public function test_strict_mode_rejects_an_over_long_write(): void
    {
        $this->reconcile([$this->desired(4, filterable: true)]);

        $id = $this->seedAppointment();

        try {
            DB::connection('tenant')->update(
                'update `appointments` set `cf_4` = ? where id = ?',
                [str_repeat('x', 191), $id],
            );
            $this->fail('STRICT_TRANS_TABLES should reject 191 characters into VARCHAR(190).');
        } catch (\Illuminate\Database\QueryException $e) {
            // 1406: Data too long. Under SQLite the same write succeeds
            // silently and the value is stored in full — which is why the
            // validator refusing first is not merely a nicety.
            $this->assertStringContainsString('1406', $e->getMessage());
        }
    }

    public function test_retiring_renames_the_column_in_place_and_keeps_the_data(): void
    {
        $this->reconcile([$this->desired(5, filterable: true)]);

        $id = $this->seedAppointment();
        DB::connection('tenant')->update('update `appointments` set `cf_5` = ? where id = ?', ['PT-2026-0042', $id]);

        $intents = $this->reconcile([$this->desired(5, filterable: true, state: CustomFieldStates::RETIRING)]);

        $this->assertSame(SchemaIntent::DROP_INDEX, $intents[0]->kind);
        $this->assertSame(SchemaIntent::RETIRE_COLUMN, $intents[1]->kind);

        // The rename is ALGORITHM=INPLACE. If it were INSTANT — which the
        // design draft originally said — MySQL would refuse it outright and
        // every retire in the product would fail.
        $this->assertNull($this->columnDefinition('appointments', 'cf_5'));
        $this->assertNotNull($this->columnDefinition('appointments', 'cf_5_retired_260904'));
        $this->assertNotContains('cf_5_idx', $this->indexNames('appointments'));

        // The data survives. Retiring a field is not a decision to destroy
        // what it held — that is a separate operator command with --confirm.
        $this->assertSame(
            'PT-2026-0042',
            DB::connection('tenant')->scalar('select `cf_5_retired_260904` from `appointments` where id = ?', [$id]),
        );
    }

    public function test_the_ceiling_produces_our_message_and_not_mysqls(): void
    {
        // A tenant must read "you have used every filterable field on this
        // plan", not "Row size too large" or "Too many keys specified".
        $snapshot = $this->introspector->snapshot('appointments');
        $ceilings = new HostCeilings(maxSecondaryIndexes: $snapshot->secondaryIndexCount());

        $intents = $this->planner->plan([$this->desired(6, filterable: true)], $snapshot, $ceilings, '260904');

        $refusals = array_values(array_filter($intents, fn ($i) => $i->isRefusal()));
        $this->assertCount(1, $refusals);
        $this->assertSame(FieldSchemaPlanner::REASON_INDEX_BUDGET, $refusals[0]->reasonCode);

        // A refusal is a decision, not an exception: apply() skips it and
        // leaves the table untouched.
        $applied = $this->reconciler->apply('appointments', $intents);
        $this->assertCount(1, $applied, 'Only the ADD COLUMN should have run.');
    }

    public function test_the_reconciler_refuses_a_column_it_does_not_own(): void
    {
        $intent = new SchemaIntent(
            kind: SchemaIntent::ADD_COLUMN,
            column: 'starts_at',
            num: 1,
            spec: $this->text->columnSpec(false),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/only ever names cf_/');

        $this->reconciler->apply('appointments', [$intent]);
    }

    private function seedAppointment(): string
    {
        $typeId = (string) \Illuminate\Support\Str::uuid();
        $statusId = (string) \Illuminate\Support\Str::uuid();
        $id = (string) \Illuminate\Support\Str::uuid();
        $now = now();

        DB::connection('tenant')->table('appointment_types')->insert([
            'id' => $typeId, 'slug' => 'visit', 'label' => 'Visit',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::connection('tenant')->table('appointment_statuses')->insert([
            'id' => $statusId, 'slug' => 'scheduled', 'label' => 'Scheduled',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::connection('tenant')->table('appointments')->insert([
            'id' => $id,
            'appointment_type_id' => $typeId,
            'appointment_status_id' => $statusId,
            'title' => 'A visit',
            'starts_at' => $now,
            'ends_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return $id;
    }
}
