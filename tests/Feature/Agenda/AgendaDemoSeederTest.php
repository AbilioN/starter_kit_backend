<?php

namespace Tests\Feature\Agenda;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Database\Seeders\AgendaDemoSeeder;
use Database\Seeders\AgendaSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\TenantTestCase;

class AgendaDemoSeederTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('demo');
        Artisan::call('db:seed', ['--class' => AgendaSeeder::class, '--force' => true]);
    }

    public function test_re_running_replaces_the_demo_rather_than_duplicating_it(): void
    {
        // A demo is re-run in front of people, sometimes twice. Doubling the
        // week each time would be the most visible possible bug.
        Artisan::call('db:seed', ['--class' => AgendaDemoSeeder::class, '--force' => true]);
        $first = Appointment::count();

        Artisan::call('db:seed', ['--class' => AgendaDemoSeeder::class, '--force' => true]);

        $this->assertSame($first, Appointment::count());
        $this->assertGreaterThan(0, $first);
    }

    public function test_it_never_touches_appointments_it_did_not_create(): void
    {
        Artisan::call('db:seed', ['--class' => AgendaDemoSeeder::class, '--force' => true]);

        $real = Appointment::first()->replicate();
        $real->title = 'A real appointment entered during the demo';
        $real->metadata = null;
        $real->save();

        Artisan::call('db:seed', ['--class' => AgendaDemoSeeder::class, '--force' => true]);

        $this->assertDatabaseHas('appointments', ['id' => $real->id]);
    }

    public function test_every_stop_is_routable_and_in_a_week_that_has_not_passed(): void
    {
        Artisan::call('db:seed', ['--class' => AgendaDemoSeeder::class, '--force' => true]);

        // Coordinates on every row: a demo where the route button fails is
        // worse than no demo.
        $this->assertSame(
            Appointment::count(),
            Appointment::routable()->count(),
        );

        // And the week must be ahead, or the first impression is a diary of
        // things that already happened.
        $this->assertTrue(
            Appointment::min('starts_at') >= CarbonImmutable::now()->startOfWeek()->toDateTimeString(),
        );
    }

    public function test_it_seeds_both_cities_so_grouping_and_routing_have_something_to_show(): void
    {
        Artisan::call('db:seed', ['--class' => AgendaDemoSeeder::class, '--force' => true]);

        $cities = Appointment::distinct()->pluck('location_city')->sort()->values()->all();

        $this->assertSame(['Lisboa', 'Porto'], $cities);
    }
}
