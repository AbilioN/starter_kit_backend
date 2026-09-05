<?php

namespace Tests\Feature\CustomFields;

use App\Application\CustomFields\FieldViewerFactory;
use App\Application\UseCases\CustomField\CreateFieldDefinitionUseCase;
use App\Application\UseCases\CustomField\ProjectCustomFieldsUseCase;
use App\Domain\CustomFields\CustomFieldStates;
use App\Infrastructure\CustomFields\CatalogueLoader;
use App\Models\Admin;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\AppointmentType;
use App\Models\CustomFieldDefinition;
use App\Models\Permission;
use App\Models\Role;
use Carbon\CarbonImmutable;
use Database\Seeders\AgendaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TenantTestCase;

/**
 * Who sees a custom value, and — much more importantly — who does not.
 *
 * The study's third pitfall is "the permission that was only a hidden input":
 * a role that must not see a field can read it from the API response anyway,
 * because the rule was implemented where somebody noticed it (the form) rather
 * than where the value is read and written.
 *
 * Every test here creates a NON-SUPER admin explicitly. `is_super_admin`
 * bypasses all of this, AdminFactory returns a SudoAdmin for those people, and
 * a freshly provisioned tenant's only admin is both super admin and tenant
 * owner — so a visibility test written against the obvious fixture passes
 * without asserting anything at all.
 */
class CustomFieldProjectionTest extends TenantTestCase
{
    private const SECRET = 'PT-2026-0042';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('cfproj');

        // Roles and permissions first: actingAsTenant() does not run the
        // provisioning seeders, so without these `appointment-read` does not
        // exist as a row and every RBAC assertion below would pass or fail for
        // the wrong reason.
        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => AgendaSeeder::class, '--force' => true]);

        // The reconciler is MySQL-only by design, so under the fast gate the
        // column is created by hand. What this suite asserts is the read path,
        // the mask and the write path — the SQL that creates the column is
        // asserted against the real engine in SchemaReconcilerMySqlTest.
        Schema::connection('tenant')->table('appointments', function ($table) {
            $table->string('cf_1', 190)->nullable();
        });

        CatalogueLoader::forget();
    }

    private function defineField(array $roleRules = []): CustomFieldDefinition
    {
        $definition = app(CreateFieldDefinitionUseCase::class)->execute(
            hostKey: 'appointments',
            fieldType: 'text',
            labels: [
                'en' => ['label' => 'Contract number', 'help_text' => null],
                'pt' => ['label' => 'Nº de contrato', 'help_text' => null],
            ],
            isFilterable: true,
            roleRules: $roleRules,
            presentation: ['slot' => 'card.badges', 'icon' => 'mdi-file-document', 'colour' => '#185FA5', 'colour_dark' => '#7EB6E8'],
        );

        // The column already exists in this fixture, so the definition is live.
        $definition->update(['state' => CustomFieldStates::LIVE]);
        CatalogueLoader::forget();

        return $definition;
    }

    private function makeAppointment(): Appointment
    {
        $appointment = Appointment::create([
            'appointment_type_id' => AppointmentType::where('slug', 'visit')->value('id'),
            'appointment_status_id' => AppointmentStatus::where('slug', 'scheduled')->value('id'),
            'title' => 'A visit',
            'starts_at' => CarbonImmutable::parse('2026-09-02 10:00'),
            'ends_at' => CarbonImmutable::parse('2026-09-02 11:00'),
        ]);

        $appointment->setTenantFieldValues(['cf_1' => self::SECRET], ['cf_1']);
        $appointment->save();

        return $appointment->refresh();
    }

    private function admin(bool $superAdmin = false, array $roleSlugs = [], array $permissionSlugs = []): Admin
    {
        $admin = Admin::factory()->create(['is_super_admin' => $superAdmin, 'is_active' => true]);

        foreach ($roleSlugs as $slug) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucfirst($slug), 'description' => $slug, 'is_active' => true],
            );

            if ($permissionSlugs !== []) {
                $role->permissions()->syncWithoutDetaching(
                    Permission::whereIn('slug', $permissionSlugs)->pluck('id'),
                );
            }
            // admin_roles carries assigned_at / assigned_by as NOT NULL — it
            // is an audit-shaped pivot, not a plain many-to-many.
            $admin->roles()->attach($role->id, [
                'assigned_at' => now(),
                'assigned_by' => $admin->id,
                'is_active' => true,
            ]);
        }

        return $admin->refresh();
    }

    public function test_a_custom_value_never_ships_through_a_raw_model(): void
    {
        // AppointmentController returns the raw Eloquent model as `data` from
        // three methods, and Appointment declares no $hidden of its own. This
        // is the leak HasTenantFields closes, and it closes it for every
        // present and future caller rather than for the three that exist.
        $appointment = $this->makeAppointment();

        $this->assertArrayNotHasKey('cf_1', $appointment->toArray());
        $this->assertStringNotContainsString(self::SECRET, $appointment->toJson());

        // And the value really is stored — otherwise this test would pass for
        // the wrong reason.
        $this->assertSame(self::SECRET, $appointment->getAttributes()['cf_1']);
    }

    public function test_the_agenda_card_carries_the_value_record_for_someone_who_may_see_it(): void
    {
        $this->defineField();
        $this->makeAppointment();

        Sanctum::actingAs($this->admin(superAdmin: true));

        $response = $this->getJson('/api/admin/agenda?view=week&date=2026-09-02')->assertOk();

        // How the field LOOKS travels once, with the screen — so the panel
        // needs no second request, and a hundred cards do not each carry a
        // copy of the tenant's presentation config.
        $descriptors = $response->json('data.custom_fields');
        $this->assertCount(1, $descriptors);

        $descriptor = $descriptors[0];
        $this->assertSame(1, $descriptor['field']);
        $this->assertSame('cf_1', $descriptor['key']);
        $this->assertSame('text', $descriptor['type']);
        $this->assertSame('Contract number', $descriptor['label']);
        $this->assertSame('mdi-file-document', $descriptor['icon']);

        // Both colours, always — the server does not know what the reader is
        // looking at.
        $this->assertSame('#185FA5', $descriptor['colour']);
        $this->assertSame('#7EB6E8', $descriptor['colour_dark']);
        $this->assertSame('card.badges', $descriptor['slot']);
        $this->assertTrue($descriptor['editable']);

        // The card carries only what differs per row, joined on `field`.
        $card = $response->json('data.groups.0.days.2.appointments.0');
        $this->assertCount(1, $card['custom']);
        $this->assertSame(
            ['field' => 1, 'key' => 'cf_1', 'value' => self::SECRET, 'text' => self::SECRET],
            $card['custom'][0],
        );
    }

    public function test_the_descriptor_block_is_not_repeated_on_every_card(): void
    {
        // The reason the payload is normalised at all. Without it, a week with
        // a hundred appointments and a dozen fields would ship the tenant's
        // labels, icons and colour pairs twelve hundred times.
        $this->defineField();

        foreach (range(1, 3) as $i) {
            $this->makeAppointment();
        }

        Sanctum::actingAs($this->admin(superAdmin: true));

        $response = $this->getJson('/api/admin/agenda?view=week&date=2026-09-02')->assertOk();

        $this->assertCount(1, $response->json('data.custom_fields'));

        foreach ($response->json('data.groups.0.days.2.appointments') as $card) {
            $this->assertArrayNotHasKey('label', $card['custom'][0]);
            $this->assertArrayNotHasKey('colour', $card['custom'][0]);
            $this->assertArrayNotHasKey('icon', $card['custom'][0]);
        }
    }

    public function test_a_hidden_field_is_absent_from_the_payload_not_nulled(): void
    {
        // The support role must be able to OPEN the agenda — otherwise this
        // asserts a 403 rather than the mask, and would keep passing if the
        // mask were removed entirely.
        $support = $this->admin(roleSlugs: ['support'], permissionSlugs: ['appointment-read']);
        $supportRoleId = Role::where('slug', 'support')->value('id');

        $this->defineField(['hidden' => [$supportRoleId]]);
        $this->makeAppointment();

        Sanctum::actingAs($support);

        $response = $this->getJson('/api/admin/agenda?view=week&date=2026-09-02')->assertOk();

        // The whole body, not a path: a leak that reaches any corner of the
        // response is still a leak.
        $this->assertStringNotContainsString(self::SECRET, $response->getContent());

        $card = $response->json('data.groups.0.days.2.appointments.0');
        $this->assertSame([], $card['custom'], 'A hidden field must be omitted, not nulled — a null still says it exists.');
    }

    public function test_deny_wins_when_one_of_two_roles_hides_the_field(): void
    {
        // The first inverted rule in a codebase where every check is
        // allow-wins. An admin holding both Support and Manager is still, at
        // that moment, sitting in Support.
        $admin = $this->admin(roleSlugs: ['support', 'manager']);
        $supportRoleId = Role::where('slug', 'support')->value('id');

        $this->defineField(['hidden' => [$supportRoleId]]);
        $appointment = $this->makeAppointment();

        $viewer = app(FieldViewerFactory::class)->forAdmin($admin);

        $projector = app(ProjectCustomFieldsUseCase::class);

        $this->assertSame([], $projector->context('appointments', $viewer));
        $this->assertSame([], $projector->values('appointments', $appointment, $viewer));
    }

    public function test_a_readonly_field_is_visible_but_not_writable(): void
    {
        $admin = $this->admin(roleSlugs: ['support']);
        $roleId = Role::where('slug', 'support')->value('id');

        $this->defineField(['readonly' => [$roleId]]);
        $appointment = $this->makeAppointment();

        $viewer = app(FieldViewerFactory::class)->forAdmin($admin);
        $projector = app(ProjectCustomFieldsUseCase::class);

        $context = $projector->context('appointments', $viewer);

        $this->assertCount(1, $context);
        $this->assertFalse($context[0]['editable']);

        // Visible, so its value still travels — a readonly field a reader
        // cannot see is a hidden field, and these are different rules.
        $this->assertCount(1, $projector->values('appointments', $appointment, $viewer));
        $this->assertSame([], $projector->writableColumns('appointments', $viewer));
    }

    public function test_a_super_admin_bypasses_every_rule(): void
    {
        $admin = $this->admin(superAdmin: true, roleSlugs: ['support']);
        $roleId = Role::where('slug', 'support')->value('id');

        $this->defineField(['hidden' => [$roleId]]);
        $appointment = $this->makeAppointment();

        $viewer = app(FieldViewerFactory::class)->forAdmin($admin);

        $this->assertCount(1, app(ProjectCustomFieldsUseCase::class)->context('appointments', $viewer));
    }

    public function test_a_rule_whose_role_was_deleted_is_inert(): void
    {
        // Roles are HARD deleted with no foreign keys, so a rule can dangle.
        // It can no longer match anyone's role set, which means a deleted role
        // can never make a field MORE restrictive by accident.
        $admin = $this->admin(roleSlugs: ['support']);

        $this->defineField(['hidden' => ['a-role-id-that-never-existed']]);
        $appointment = $this->makeAppointment();

        $viewer = app(FieldViewerFactory::class)->forAdmin($admin);

        $this->assertCount(1, app(ProjectCustomFieldsUseCase::class)->context('appointments', $viewer));
    }

    public function test_an_unwritable_column_is_discarded_rather_than_stored(): void
    {
        // $fillable is a fixed list and cf_* columns are invented at runtime,
        // so update(['cf_1' => ...]) silently discards the key and answers 200
        // with nothing stored. setTenantFieldValues takes an explicit
        // whitelist for exactly that reason.
        $appointment = $this->makeAppointment();

        $appointment->setTenantFieldValues(['cf_1' => 'overwritten'], []);
        $appointment->save();

        $this->assertSame(self::SECRET, $appointment->refresh()->getAttributes()['cf_1']);
    }

    public function test_the_label_follows_the_readers_language(): void
    {
        $this->defineField();
        $appointment = $this->makeAppointment();

        $viewer = app(FieldViewerFactory::class)->forAdmin($this->admin(superAdmin: true));
        $projector = app(ProjectCustomFieldsUseCase::class);

        app()->setLocale('pt');
        $this->assertSame('Nº de contrato', $projector->context('appointments', $viewer)[0]['label']);

        app()->setLocale('en');
        $this->assertSame('Contract number', $projector->context('appointments', $viewer)[0]['label']);

        // A locale with no translation written falls through the cascade that
        // already exists rather than answering blank — the step that matters
        // when a tenant enables four languages and writes one.
        app()->setLocale('es');
        $this->assertNotSame('', $projector->context('appointments', $viewer)[0]['label']);
    }

    public function test_a_custom_value_can_be_written_and_read_back_on_a_user(): void
    {
        // The second host. `users` is where the frozen line is tightest — the
        // e-mail, the password and the verification timestamp are all core —
        // so the thing worth asserting is that a tenant-invented column is
        // written and read while none of that moves.
        Schema::connection('tenant')->table('users', function ($table) {
            $table->string('cf_1', 190)->nullable();
        });

        app(CreateFieldDefinitionUseCase::class)->execute(
            hostKey: 'users',
            fieldType: 'text',
            labels: ['en' => ['label' => 'Notice period']],
        )->update(['state' => CustomFieldStates::LIVE]);

        CatalogueLoader::forget();

        $admin = $this->admin(superAdmin: true);
        Sanctum::actingAs($admin);

        $user = \App\Models\User::factory()->create(['name' => 'Ana', 'email' => 'ana@tenant.test']);

        $this->putJson("/api/admin/users/{$user->id}", ['custom' => ['cf_1' => '30 days']])
            ->assertOk()
            ->assertJsonPath('custom.0.value', '30 days')
            ->assertJsonPath('custom_fields.0.label', 'Notice period');

        // The credentials did not move, and the value is not in the raw model.
        $user->refresh();
        $this->assertSame('ana@tenant.test', $user->email);
        $this->assertArrayNotHasKey('cf_1', $user->toArray());
        $this->assertSame('30 days', $user->getAttributes()['cf_1']);
    }

    public function test_a_custom_value_on_a_user_never_reaches_the_immutable_audit_log(): void
    {
        // The leak with no later fix. User uses HasAuditLog, which strips
        // $hidden before writing oldValues/newValues — and audit_logs is
        // immutable by cross-cutting decision: no delete, no update, not even
        // for a super admin. Custom fields are exactly where a tenant puts a
        // national ID.
        Schema::connection('tenant')->table('users', function ($table) {
            $table->string('cf_1', 190)->nullable();
        });

        app(CreateFieldDefinitionUseCase::class)->execute(
            hostKey: 'users',
            fieldType: 'text',
            labels: ['en' => ['label' => 'National ID']],
        )->update(['state' => CustomFieldStates::LIVE]);

        CatalogueLoader::forget();

        $admin = $this->admin(superAdmin: true);
        Sanctum::actingAs($admin);

        $user = \App\Models\User::factory()->create();

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Renamed',
            'custom' => ['cf_1' => self::SECRET],
        ])->assertOk();

        $rows = \Illuminate\Support\Facades\DB::connection('tenant')->table('audit_logs')->get();

        foreach ($rows as $row) {
            $this->assertStringNotContainsString(self::SECRET, json_encode($row));
        }
    }
}
