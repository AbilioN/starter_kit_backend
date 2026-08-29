<?php

namespace Tests\Feature\Agenda;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\AppointmentType;
use Carbon\CarbonImmutable;
use Database\Seeders\AgendaSeeder;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TenantTestCase;

/**
 * The agenda as a screen: the grid, the counts, the cards and their menus.
 */
class AgendaTest extends TenantTestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('agenda');
        Artisan::call('db:seed', ['--class' => AgendaSeeder::class, '--force' => true]);

        $this->admin = Admin::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        Sanctum::actingAs($this->admin);
    }

    private function makeAppointment(array $overrides = []): Appointment
    {
        return Appointment::create([
            'appointment_type_id' => AppointmentType::where('slug', 'visit')->value('id'),
            'appointment_status_id' => AppointmentStatus::where('slug', 'scheduled')->value('id'),
            'title' => 'A visit',
            'starts_at' => CarbonImmutable::parse('2026-09-02 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-09-02 11:00'),
            ...$overrides,
        ]);
    }

    public function test_the_week_view_returns_seven_days_with_counts(): void
    {
        $this->makeAppointment();
        $this->makeAppointment([
            'appointment_status_id' => AppointmentStatus::where('slug', 'confirmed')->value('id'),
            'title' => 'A confirmed visit',
        ]);

        $response = $this->getJson('/api/admin/agenda?view=week&date=2026-09-02')->assertOk();

        $days = $response->json('data.groups.0.days');
        $this->assertCount(7, $days);

        // Counts sit next to every axis: the agenda doubles as the daily
        // dashboard, so "2 appointments, 1 confirmed" is part of the payload
        // rather than something the client recomputes.
        $response->assertJsonPath('data.totals.count', 2)
                 ->assertJsonPath('data.totals.confirmed', 1);
    }

    public function test_an_appointment_spanning_two_days_appears_on_both(): void
    {
        // The case a column-per-appointment design has to expand rows in PHP to
        // fake. With a real interval it falls out of an overlap query.
        $this->makeAppointment([
            'title' => 'Two-day fair',
            'starts_at' => CarbonImmutable::parse('2026-09-02 09:00'),
            'ends_at' => CarbonImmutable::parse('2026-09-03 18:00'),
        ]);

        $days = $this->getJson('/api/admin/agenda?view=week&date=2026-09-02')
            ->assertOk()
            ->json('data.groups.0.days');

        $byDate = collect($days)->keyBy('date');
        $this->assertSame(1, $byDate['2026-09-02']['count']);
        $this->assertSame(1, $byDate['2026-09-03']['count'], 'The second day must show it too.');
        $this->assertTrue($byDate['2026-09-02']['appointments'][0]['spans_days']);
    }

    public function test_the_day_view_clamps_out_of_hours_appointments_into_the_edges(): void
    {
        // An 06:00 appointment must still be visible on its day. Dropping it
        // would make the grid lie about what is booked.
        $this->makeAppointment([
            'starts_at' => CarbonImmutable::parse('2026-09-02 06:00'),
            'ends_at' => CarbonImmutable::parse('2026-09-02 07:00'),
        ]);

        $hours = $this->getJson('/api/admin/agenda?view=day&date=2026-09-02')
            ->assertOk()
            ->json('data.groups.0.hours');

        $first = collect($hours)->firstWhere('hour', 7);
        $this->assertSame(1, $first['count']);
    }

    public function test_the_month_view_carries_counts_but_not_cards(): void
    {
        // The cheapest view and the one people navigate with; a wall of cards
        // for a whole month is something nobody reads.
        $this->makeAppointment();

        $days = $this->getJson('/api/admin/agenda?view=month&date=2026-09-02')
            ->assertOk()
            ->json('data.groups.0.days');

        $day = collect($days)->firstWhere('date', '2026-09-02');
        $this->assertSame(1, $day['count']);
        $this->assertNull($day['appointments']);
    }

    public function test_grouping_splits_the_grid_and_labels_the_empty_bucket(): void
    {
        $other = Admin::factory()->create();
        $this->makeAppointment(['assigned_admin_id' => $other->id]);
        $this->makeAppointment(); // unassigned

        $groups = $this->getJson('/api/admin/agenda?view=week&date=2026-09-02&group_by=assigned_admin')
            ->assertOk()
            ->json('data.groups');

        $this->assertCount(2, $groups);
        // Never a blank heading — an unlabelled bucket reads as a bug.
        $this->assertContains('[Unassigned]', array_column($groups, 'label'));
    }

    public function test_a_card_carries_its_menu_grouped_into_sub_menus(): void
    {
        $this->makeAppointment([
            'location_lat' => 48.8566,
            'location_lng' => 2.3522,
            'metadata' => ['phone' => '+33 6 12 34 56 78'],
        ]);

        $card = $this->getJson('/api/admin/agenda?view=week&date=2026-09-02')
            ->assertOk()
            ->json('data.groups.0.days.2.appointments.0');

        $this->assertArrayHasKey('export', $card['actions']);
        $this->assertArrayHasKey('contact', $card['actions']);
        $this->assertArrayHasKey('planning', $card['actions']);
        $this->assertContains('calendar.google', array_column($card['actions']['export'], 'key'));
    }

    public function test_an_action_is_absent_when_it_could_not_work(): void
    {
        // No phone, no coordinates: an action that appears and then fails is
        // worse than one that is not offered.
        $this->makeAppointment();

        $card = $this->getJson('/api/admin/agenda?view=week&date=2026-09-02')
            ->assertOk()
            ->json('data.groups.0.days.2.appointments.0');

        $this->assertArrayNotHasKey('contact', $card['actions']);
        $this->assertArrayNotHasKey('planning', $card['actions']);
    }

    public function test_status_can_be_changed_in_one_call(): void
    {
        $appointment = $this->makeAppointment();
        $confirmed = AppointmentStatus::where('slug', 'confirmed')->value('id');

        $this->patchJson("/api/admin/appointments/{$appointment->id}/status", [
            'appointment_status_id' => $confirmed,
        ])->assertOk()->assertJsonPath('data.status.slug', 'confirmed');
    }
}
