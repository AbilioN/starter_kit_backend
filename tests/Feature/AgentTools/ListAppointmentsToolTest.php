<?php

namespace Tests\Feature\AgentTools;

use App\Application\AgentTools\ListAppointmentsTool;
use App\Application\UseCases\CustomField\CreateFieldDefinitionUseCase;
use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\Exceptions\AgentToolFailure;
use App\Domain\CustomFields\CustomFieldStates;
use App\Infrastructure\CustomFields\CatalogueLoader;
use App\Models\Admin;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\AppointmentType;
use App\Models\Permission;
use App\Models\Role;
use Carbon\CarbonImmutable;
use Database\Seeders\AgendaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * What the assistant may say about the agenda.
 *
 * The load-bearing test is the masking one, and it is written against a
 * NON-super admin on purpose: a freshly provisioned tenant's only admin is
 * `is_super_admin`, bypasses every rule, and would make the assertion pass
 * while proving nothing.
 */
class ListAppointmentsToolTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('apptool');

        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => AgendaSeeder::class, '--force' => true]);

        // The reconciler is MySQL-only, so the column is made by hand. What
        // this suite asserts is what the tool says, not what the ALTER did.
        Schema::connection('tenant')->table('appointments', function ($table) {
            $table->string('cf_1', 190)->nullable();
        });

        CatalogueLoader::forget();
    }

    private function makeAppointment(array $overrides = []): Appointment
    {
        return Appointment::create([
            'appointment_type_id' => AppointmentType::where('slug', 'visit')->value('id'),
            'appointment_status_id' => AppointmentStatus::where('slug', 'scheduled')->value('id'),
            'title' => 'A visit',
            'starts_at' => CarbonImmutable::parse('2026-09-10 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-09-10 11:00'),
            ...$overrides,
        ]);
    }

    private function admin(bool $superAdmin, array $roleSlugs = []): Admin
    {
        $admin = Admin::factory()->create(['is_super_admin' => $superAdmin, 'is_active' => true]);

        foreach ($roleSlugs as $slug) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucfirst($slug), 'description' => $slug, 'is_active' => true],
            );
            $role->permissions()->syncWithoutDetaching(
                Permission::whereIn('slug', ['appointment-read'])->pluck('id')
            );
            $admin->roles()->attach($role->id, [
                'assigned_at' => now(), 'assigned_by' => $admin->id, 'is_active' => true,
            ]);
        }

        return $admin->refresh();
    }

    private function defineField(string $label, array $roleRules = []): void
    {
        $definition = app(CreateFieldDefinitionUseCase::class)->execute(
            hostKey: 'appointments',
            fieldType: 'text',
            labels: ['en' => ['label' => $label]],
            isFilterable: false,
            roleRules: $roleRules,
            presentation: [],
        );

        $definition->update(['state' => CustomFieldStates::LIVE]);

        CatalogueLoader::forget();
    }

    /** @return array<int, array<string, mixed>> */
    private function runTool(Admin $actor, array $arguments = [], string $actorType = 'admin'): array
    {
        $context = new AgentToolContext(
            tenantId: 'tenant-under-test',
            actorId: $actor->id,
            actorType: $actorType,
            chatId: 'chat-1',
            requestId: 'req-1',
            impersonatedBy: null,
            maxRows: 50,
        );

        return app(ListAppointmentsTool::class)->execute($arguments, $context)->value;
    }

    public function test_it_lists_what_is_scheduled_on_the_day_asked_for(): void
    {
        $this->makeAppointment(['title' => 'Tasting for the Silva wedding']);

        $rows = $this->runTool($this->admin(superAdmin: true), ['from' => '2026-09-10']);

        $this->assertCount(1, $rows);
        $this->assertSame('Tasting for the Silva wedding', $rows[0]['title']);
        $this->assertSame('Visit', $rows[0]['type']);
        $this->assertArrayHasKey('starts_at', $rows[0]);
    }

    public function test_it_leaves_out_what_falls_outside_the_window(): void
    {
        $this->makeAppointment(['title' => 'On the day']);
        $this->makeAppointment([
            'title' => 'A week later',
            'starts_at' => CarbonImmutable::parse('2026-09-17 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-09-17 11:00'),
        ]);

        $rows = $this->runTool($this->admin(superAdmin: true), ['from' => '2026-09-10', 'to' => '2026-09-10']);

        $this->assertSame(['On the day'], array_column($rows, 'title'));
    }

    public function test_a_range_spanning_days_is_inclusive_at_both_ends(): void
    {
        $this->makeAppointment(['title' => 'First']);
        $this->makeAppointment([
            'title' => 'Last',
            'starts_at' => CarbonImmutable::parse('2026-09-12 09:00'),
            'ends_at' => CarbonImmutable::parse('2026-09-12 10:00'),
        ]);

        $rows = $this->runTool($this->admin(superAdmin: true), ['from' => '2026-09-10', 'to' => '2026-09-12']);

        $this->assertSame(['First', 'Last'], array_column($rows, 'title'));
    }

    public function test_it_never_reports_a_custom_field_hidden_from_the_actors_roles(): void
    {
        $support = $this->admin(superAdmin: false, roleSlugs: ['support']);
        $roleId = Role::where('slug', 'support')->value('id');

        $this->defineField('Deposit taken', roleRules: ['hidden' => [$roleId]]);

        $appointment = $this->makeAppointment(['title' => 'A booking']);
        $appointment->forceFill(['cf_1' => '30% paid'])->save();

        $rows = $this->runTool($support, ['from' => '2026-09-10']);

        $this->assertCount(1, $rows);
        $this->assertArrayNotHasKey('Deposit taken', $rows[0]['custom'] ?? []);
        $this->assertStringNotContainsString(
            '30% paid',
            json_encode($rows[0]),
            'A hidden value must not reach the assistant under any key, at any depth.',
        );
    }

    public function test_a_visible_custom_field_is_reported_under_the_tenants_own_label(): void
    {
        // `cf_1` means nothing to a model or to the person reading its answer.
        $this->defineField('Deposit taken');

        $appointment = $this->makeAppointment();
        $appointment->forceFill(['cf_1' => '30% paid'])->save();

        $rows = $this->runTool($this->admin(superAdmin: true), ['from' => '2026-09-10']);

        $this->assertSame('30% paid', $rows[0]['custom']['Deposit taken'] ?? null);
        $this->assertArrayNotHasKey('cf_1', $rows[0]);
    }

    public function test_a_tenant_label_cannot_shadow_a_core_field(): void
    {
        // The label is a string the tenant chose, and the row has reserved
        // keys. Merged in flat, a field named "title" would overwrite the
        // appointment's own — and one named "confirmed" could flip an
        // availability answer.
        $this->defineField('title');

        $appointment = $this->makeAppointment(['title' => 'The real title']);
        $appointment->forceFill(['cf_1' => 'not the title'])->save();

        $rows = $this->runTool($this->admin(superAdmin: true), ['from' => '2026-09-10']);

        $this->assertSame('The real title', $rows[0]['title']);
        $this->assertSame('not the title', $rows[0]['custom']['title']);
    }

    public function test_it_fails_closed_when_the_actor_is_not_an_admin(): void
    {
        // forAdmin(null) returns FieldViewer::system(), which bypasses every
        // hide rule — so the fallback has to be a refusal, not a viewer.
        $this->expectException(AgentToolFailure::class);

        $this->runTool($this->admin(superAdmin: true), ['from' => '2026-09-10'], actorType: 'user');
    }

    public function test_it_refuses_a_range_too_wide_to_be_a_real_question(): void
    {
        $this->expectException(AgentToolFailure::class);

        $this->runTool($this->admin(superAdmin: true), ['from' => '2026-01-01', 'to' => '2026-12-31']);
    }

    public function test_it_refuses_a_backwards_range_rather_than_returning_nothing(): void
    {
        // Silently returning [] would read to the model as "nothing is booked".
        $this->expectException(AgentToolFailure::class);

        $this->runTool($this->admin(superAdmin: true), ['from' => '2026-09-12', 'to' => '2026-09-10']);
    }

    public function test_it_reads_the_permission_and_is_not_mutating(): void
    {
        $tool = app(ListAppointmentsTool::class);

        $this->assertSame('appointment-read', $tool->permission());
        $this->assertFalse($tool->isMutating());
    }

    public function test_its_schema_survives_the_wire(): void
    {
        // Parameters serialising as [] rather than an object fails the WHOLE
        // turn, for every other tool too — the my_profile bug of 2026-08-29.
        $parameters = app(ListAppointmentsTool::class)->parameters();

        $this->assertSame('object', $parameters['type']);
        $this->assertArrayHasKey('from', $parameters['properties']);
        $this->assertNotSame([], json_decode(json_encode($parameters), true)['properties']);
    }
}
