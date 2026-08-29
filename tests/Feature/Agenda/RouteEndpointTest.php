<?php

namespace Tests\Feature\Agenda;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\AppointmentType;
use App\Models\MapsUsageLog;
use Carbon\CarbonImmutable;
use Database\Seeders\AgendaSeeder;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TenantTestCase;

class RouteEndpointTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('routes');
        Artisan::call('db:seed', ['--class' => AgendaSeeder::class, '--force' => true]);
        Sanctum::actingAs(Admin::factory()->create(['is_super_admin' => true, 'is_active' => true]));
    }

    private function stopAt(string $title, ?float $lat, ?float $lng): Appointment
    {
        return Appointment::create([
            'appointment_type_id' => AppointmentType::where('slug', 'visit')->value('id'),
            'appointment_status_id' => AppointmentStatus::where('slug', 'scheduled')->value('id'),
            'title' => $title,
            'starts_at' => CarbonImmutable::parse('2026-09-02 09:00'),
            'ends_at' => CarbonImmutable::parse('2026-09-02 10:00'),
            'location_lat' => $lat,
            'location_lng' => $lng,
        ]);
    }

    public function test_it_returns_an_ordered_roadmap_with_per_leg_figures(): void
    {
        // The deliverable is not a line on a map — it is an ordered list a
        // driver can follow, with a distance and a time against every hop.
        $a = $this->stopAt('Depot', 0.0, 0.0);
        $c = $this->stopAt('Far', 0.0, 0.2);
        $b = $this->stopAt('Near', 0.0, 0.1);

        $response = $this->postJson('/api/admin/routes/optimize', [
            'appointment_ids' => [$a->id, $c->id, $b->id],
            'origin_appointment_id' => $a->id,
        ])->assertOk();

        $this->assertSame(
            ['Depot', 'Near', 'Far'],
            array_column($response->json('data.stops'), 'label'),
        );

        $this->assertCount(2, $response->json('data.legs'));
        $this->assertGreaterThan(0, $response->json('data.total_distance_meters'));
        $this->assertTrue($response->json('data.estimated'));
    }

    public function test_a_round_trip_closes_the_loop(): void
    {
        $depot = $this->stopAt('Depot', 0.0, 0.0);
        $x = $this->stopAt('X', 0.1, 0.1);
        $y = $this->stopAt('Y', 0.2, 0.0);

        $stops = $this->postJson('/api/admin/routes/optimize', [
            'appointment_ids' => [$depot->id, $x->id, $y->id],
            'origin_appointment_id' => $depot->id,
            'round_trip' => true,
        ])->assertOk()->json('data.stops');

        $this->assertSame('Depot', $stops[0]['label']);
        $this->assertSame('Depot', $stops[count($stops) - 1]['label']);
    }

    public function test_too_many_stops_is_refused_with_a_message_never_truncated(): void
    {
        // A route quietly missing three stops looks correct, and someone drives
        // it. So the cap refuses and says what to do.
        config(['routing.max_stops' => 3]);

        $ids = collect(range(1, 4))
            ->map(fn (int $i) => $this->stopAt("Stop {$i}", 0.0, $i / 10)->id)
            ->all();

        $this->postJson('/api/admin/routes/optimize', ['appointment_ids' => $ids])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_appointments_without_coordinates_cannot_be_routed(): void
    {
        $a = $this->stopAt('Geocoded', 0.0, 0.0);
        $b = $this->stopAt('Not geocoded', null, null);

        $this->postJson('/api/admin/routes/optimize', ['appointment_ids' => [$a->id, $b->id]])
            ->assertStatus(422);
    }

    public function test_the_local_provider_is_not_metered(): void
    {
        // Nothing was bought, so there is nothing to bill or throttle. The
        // ledger exists for calls made on the platform's own key.
        $a = $this->stopAt('A', 0.0, 0.0);
        $b = $this->stopAt('B', 0.0, 0.1);

        $this->postJson('/api/admin/routes/optimize', [
            'appointment_ids' => [$a->id, $b->id],
        ])->assertOk();

        $this->assertSame(0, MapsUsageLog::count());
    }
}
