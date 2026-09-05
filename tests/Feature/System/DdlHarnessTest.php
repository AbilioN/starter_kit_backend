<?php

namespace Tests\Feature\System;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\DdlTestCase;

/**
 * Proves the harness before anything relies on it.
 *
 * The claim DdlTestCase makes is narrow and load-bearing: a test may run real
 * DDL, MySQL will implicitly commit it, and none of it reaches the shared
 * `starter_kit_testing_tenant` that the other ~600 test methods run against.
 * If that claim is wrong, the symptom is not a failure here — it is a hundred
 * unrelated tests failing later, which is exactly the debugging session this
 * class exists to prevent.
 */
#[Group('mysql-ddl')]
class DdlHarnessTest extends DdlTestCase
{
    /** Carried between methods so the second can check the first was cleaned up. */
    private static ?string $firstDatabase = null;

    public function test_ddl_lands_in_this_tests_own_database_and_not_in_the_shared_one(): void
    {
        self::$firstDatabase = $this->ddlDatabase;

        DB::connection('tenant')->statement(
            'ALTER TABLE `admins` ADD COLUMN `ddl_probe` VARCHAR(10) NULL, ALGORITHM=INSTANT'
        );

        // In this test's own database: present.
        $this->assertNotNull(
            $this->columnDefinition('admins', 'ddl_probe'),
            'The ALTER did not reach the throwaway database.',
        );

        // In the database every other test uses: absent. Read from
        // information_schema rather than from the connection, because the
        // question is about committed schema, not about this transaction.
        $leaked = DB::connection('landlord')->selectOne(
            'select column_name from information_schema.columns
              where table_schema = ? and table_name = ? and column_name = ?',
            [$this->sharedTenantDatabase, 'admins', 'ddl_probe'],
        );

        $this->assertNull(
            $leaked,
            "DDL escaped into the shared tenant database ({$this->sharedTenantDatabase}). "
            .'Every test after this one is now running against a mutated schema.',
        );
    }

    public function test_the_previous_tests_throwaway_database_was_dropped(): void
    {
        $this->assertNotNull(self::$firstDatabase, 'Expected the previous test to have run first.');
        $this->assertNotSame(self::$firstDatabase, $this->ddlDatabase, 'Each test must own a fresh database.');

        $still = DB::connection('landlord')->select(
            'select schema_name from information_schema.schemata where schema_name = ?',
            [self::$firstDatabase],
        );

        $this->assertSame(
            [],
            $still,
            'TenantTestCase::tearDown() did not drop the throwaway database; they will accumulate.',
        );
    }

    public function test_the_tenant_connection_is_usable_again_after_a_ddl_test(): void
    {
        // The bug this guards: tearDown drops the throwaway, and if the
        // connection is still pointed at it, truncateAll('tenant') throws,
        // gets swallowed, and silently truncates nothing.
        $this->assertSame(
            $this->ddlDatabase,
            DB::connection('tenant')->getDatabaseName(),
            'The tenant connection should be on this test\'s own database during the test.',
        );
    }
}
