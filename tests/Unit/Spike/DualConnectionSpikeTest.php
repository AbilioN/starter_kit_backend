<?php

namespace Tests\Unit\Spike;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * Throwaway spike proving the landlord/tenant dual-connection test setup
 * (see TenantTestCase) actually isolates connections and rolls back
 * between tests. Delete once TenantProvisioningTest (Sprint 0.2) exercises
 * the same guarantees against real schemas.
 */
class DualConnectionSpikeTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['landlord', 'tenant'] as $connection) {
            if (! Schema::connection($connection)->hasTable('spike_probe')) {
                Schema::connection($connection)->create('spike_probe', function ($table) {
                    $table->id();
                    $table->string('label');
                });
            }
        }
    }

    public function test_writes_on_one_connection_are_not_visible_on_the_other(): void
    {
        DB::connection('landlord')->table('spike_probe')->insert(['label' => 'landlord-row']);
        DB::connection('tenant')->table('spike_probe')->insert(['label' => 'tenant-row']);

        $this->assertSame(
            ['landlord-row'],
            DB::connection('landlord')->table('spike_probe')->pluck('label')->all()
        );
        $this->assertSame(
            ['tenant-row'],
            DB::connection('tenant')->table('spike_probe')->pluck('label')->all()
        );
    }

    public function test_previous_test_rows_do_not_leak_into_this_test(): void
    {
        $this->assertSame(0, DB::connection('landlord')->table('spike_probe')->count());
        $this->assertSame(0, DB::connection('tenant')->table('spike_probe')->count());
    }

    public function test_default_connection_flip_resolves_unqualified_queries_to_tenant(): void
    {
        // No DB::purge() here: the target database *value* isn't changing,
        // only which connection name "default" points to, so the already
        // -open 'tenant' connection (and its in-flight test transaction)
        // is reused as-is. Purging is only needed in production when
        // IdentifyTenant actually repoints database.connections.tenant.database
        // at a different tenant's database file/name.
        config(['database.default' => 'tenant']);

        DB::table('spike_probe')->insert(['label' => 'via-default']);

        $this->assertSame(
            ['via-default'],
            DB::connection('tenant')->table('spike_probe')->pluck('label')->all()
        );
        $this->assertSame(0, DB::connection('landlord')->table('spike_probe')->count());
    }
}
