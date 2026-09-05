<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RequiresMySql;

/**
 * Base case for tests that run REAL DDL against a tenant database.
 *
 * ## Why this exists
 *
 * TenantTestCase's MySQL cleanup fires only when a **new database appeared**
 * during the test (an array_diff over `show databases`). That is the right
 * trigger for tenant provisioning, and doing it unconditionally is not an
 * option — its own docblock records that truncating every table on every test
 * turned a 90-second suite into a 45-minute one.
 *
 * But a test that merely `ALTER`s a table creates no database. So the
 * cleanup is skipped — while MySQL has *already* implicitly committed the
 * enclosing RefreshDatabase transaction. The column stays for every later
 * test in the process, with no cleanup and no warning, and the symptom is
 * the next hundred unrelated tests failing on rows they never inserted.
 *
 * Rather than widen that net for all 605 test methods, this class works with
 * it: every DDL test gets its **own throwaway database**, created after
 * `parent::setUp()` has already captured the "before" list. The throwaway
 * therefore shows up in the diff, the existing teardown drops it, and the
 * shared `starter_kit_testing_tenant` is never altered at all.
 *
 * ## Two rules that follow, and are not optional
 *
 * 1. **Nothing may be seeded into the shared tenant before the switch.**
 *    Repointing the connection purges it, which closes the connection the
 *    RefreshDatabase transaction lives on — anything written before that
 *    point is discarded. Call `actingAsTenant()` and create fixtures AFTER
 *    `setUp()` has run, i.e. inside the test body.
 * 2. **Assert against `information_schema`, not against the transaction.**
 *    DDL has committed; a rolled-back transaction proves nothing about what
 *    the reconciler actually did.
 */
abstract class DdlTestCase extends TenantTestCase
{
    use RequiresMySql;

    /** The database this test owns, and is free to destroy. */
    protected string $ddlDatabase;

    /**
     * The suite's shared tenant database — where the connection pointed
     * before we moved it. Exposed so a test can assert directly that its own
     * DDL did not reach it, which is the whole claim this class makes.
     */
    protected ?string $sharedTenantDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessMySql();

        $this->sharedTenantDatabase = config('database.connections.tenant.database');

        // uniqid() rather than a fixed name: two tests in one process must not
        // collide, and a leftover from a killed run must not be adopted as if
        // it were freshly migrated.
        $this->ddlDatabase = 'sk_ddl_'.uniqid();

        DB::connection('landlord')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$this->ddlDatabase}`"
        );

        $this->pointTenantAt($this->ddlDatabase);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        // Restore BEFORE parent::tearDown(). The parent drops the throwaway
        // and then calls truncateAll('tenant') — which resolves the tenant
        // connection. Left pointing at the database it just dropped, that
        // call throws, is swallowed, and silently truncates nothing: the
        // shared tenant would then keep whatever this test left in it, which
        // is the exact failure this class was written to prevent.
        if ($this->sharedTenantDatabase !== null) {
            $this->pointTenantAt($this->sharedTenantDatabase);
        }

        parent::tearDown();
    }

    private function pointTenantAt(string $database): void
    {
        config(['database.connections.tenant.database' => $database]);
        config(['database.default' => 'tenant']);
        DB::purge('tenant');
    }

    /**
     * The real column definition, straight from the engine — the only source
     * that can answer "is it varchar(190) or varchar(70)", which is precisely
     * the question SQLite cannot be asked.
     *
     * @return array<string, mixed>|null
     */
    protected function columnDefinition(string $table, string $column): ?array
    {
        $row = DB::connection('tenant')->selectOne(
            'select column_name, column_type, is_nullable, character_maximum_length
               from information_schema.columns
              where table_schema = ? and table_name = ? and column_name = ?',
            [$this->ddlDatabase, $table, $column],
        );

        // information_schema answers with UPPERCASE keys on MySQL 8.
        return $row ? array_change_key_case((array) $row, CASE_LOWER) : null;
    }

    /**
     * @return array<int, string> index names on the table
     */
    protected function indexNames(string $table): array
    {
        return array_map(
            fn ($row) => (string) array_change_key_case((array) $row, CASE_LOWER)['index_name'],
            DB::connection('tenant')->select(
                'select distinct index_name from information_schema.statistics
                  where table_schema = ? and table_name = ?',
                [$this->ddlDatabase, $table],
            ),
        );
    }
}
