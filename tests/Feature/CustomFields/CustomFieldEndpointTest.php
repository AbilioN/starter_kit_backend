<?php

namespace Tests\Feature\CustomFields;

use App\Jobs\ReconcileTenantFieldSchema;
use App\Models\Admin;
use App\Models\CustomFieldDefinition;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TenantTestCase;

/**
 * The configuration screen's endpoints.
 *
 * Separate from the entity endpoints for a reason worth restating: reading an
 * appointment needs the fields as CONTEXT and gets them inside the agenda's
 * own response, so the panel makes no second request. This controller is the
 * other job — inventing fields — with a different permission and a much
 * larger payload.
 */
class CustomFieldEndpointTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('cfapi');

        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

        // The reconciler is MySQL-only and the queue runs sync under test, so
        // the job is faked here. What it actually DOES is asserted against the
        // real engine in SchemaReconcilerMySqlTest; what matters here is that
        // it is dispatched at all.
        Bus::fake([ReconcileTenantFieldSchema::class]);
    }

    private function adminWith(array $permissionSlugs): Admin
    {
        $admin = Admin::factory()->create(['is_super_admin' => false, 'is_active' => true]);

        $role = Role::firstOrCreate(
            ['slug' => 'fields-'.uniqid()],
            ['name' => 'Fields', 'description' => 'fields', 'is_active' => true],
        );
        $role->permissions()->sync(Permission::whereIn('slug', $permissionSlugs)->pluck('id'));

        $admin->roles()->attach($role->id, [
            'assigned_at' => now(),
            'assigned_by' => $admin->id,
            'is_active' => true,
        ]);

        return $admin->refresh();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'host' => 'appointments',
            'field_type' => 'text',
            'is_filterable' => true,
            'slot' => 'card.badges',
            'section' => 'general',
            'icon' => 'mdi-file-document',
            'colour' => '#185FA5',
            'colour_dark' => '#7EB6E8',
            'labels' => [
                'en' => ['label' => 'Contract number'],
                'pt' => ['label' => 'Nº de contrato'],
            ],
        ], $overrides);
    }

    public function test_the_configuration_payload_carries_everything_the_screen_needs(): void
    {
        Sanctum::actingAs($this->adminWith(['custom-field-read']));

        $data = $this->getJson('/api/admin/custom-fields')->assertOk()->json('data');

        $this->assertArrayHasKey('definitions', $data);
        $this->assertArrayHasKey('hosts', $data);
        $this->assertArrayHasKey('types', $data);
        // Roles ride along so the per-role matrix does not demand the
        // unrelated `role-read` slug of everyone allowed to define a field.
        $this->assertArrayHasKey('roles', $data);
        $this->assertArrayHasKey('locales', $data);

        $this->assertSame('appointments', $data['hosts'][0]['key']);
        $this->assertArrayHasKey('card.badges', $data['hosts'][0]['slots']);

        // The budget is drawn before the tenant types anything — discovering a
        // limit mid-save is how a tenant stops trusting the feature.
        $this->assertArrayHasKey('budget', $data['hosts'][0]);
        $this->assertSame(0, $data['hosts'][0]['budget']['used']);

        $this->assertSame([['key' => 'text', 'can_filter' => true]], $data['types']);
    }

    public function test_creating_a_field_answers_202_pending_and_queues_the_reconcile(): void
    {
        Sanctum::actingAs($this->adminWith(['custom-field-read', 'custom-field-manage']));

        $response = $this->postJson('/api/admin/custom-fields', $this->payload())
            ->assertStatus(202);

        // 202, not 201. The column does not exist yet: MySQL commits
        // implicitly on DDL, so the row and its ALTER cannot be one atomic
        // act, and pretending otherwise is a lie only SQLite would confirm.
        $response->assertJsonPath('data.state', 'pending')
            ->assertJsonPath('data.field', 1)
            ->assertJsonPath('data.key', 'cf_1')
            ->assertJsonPath('data.labels.pt.label', 'Nº de contrato');

        Bus::assertDispatched(ReconcileTenantFieldSchema::class);

        $this->assertDatabaseHas('custom_field_definitions', [
            'host' => 'appointments',
            'num' => 1,
            'column_name' => 'cf_1',
            'state' => 'pending',
        ]);
    }

    public function test_num_is_never_reused_after_a_field_is_retired(): void
    {
        Sanctum::actingAs($this->adminWith(['custom-field-read', 'custom-field-manage']));

        $this->postJson('/api/admin/custom-fields', $this->payload())->assertStatus(202);

        CustomFieldDefinition::query()->update(['state' => 'retired']);

        $this->postJson('/api/admin/custom-fields', $this->payload(['labels' => ['en' => ['label' => 'Second']]]))
            ->assertStatus(202)
            // Reusing handle 1 would silently hand the new field the old one's
            // parked column, its data and its audit history.
            ->assertJsonPath('data.field', 2)
            ->assertJsonPath('data.key', 'cf_2');
    }

    public function test_defining_a_field_requires_the_manage_permission(): void
    {
        // Reading the catalogue is nearly universal; running DDL is not.
        Sanctum::actingAs($this->adminWith(['custom-field-read']));

        $this->postJson('/api/admin/custom-fields', $this->payload())->assertStatus(403);
    }

    public function test_an_unknown_host_or_type_is_refused(): void
    {
        Sanctum::actingAs($this->adminWith(['custom-field-read', 'custom-field-manage']));

        $this->postJson('/api/admin/custom-fields', $this->payload(['host' => 'invoices']))
            ->assertStatus(422)->assertJsonValidationErrors('host');

        $this->postJson('/api/admin/custom-fields', $this->payload(['field_type' => 'wysiwyg']))
            ->assertStatus(422)->assertJsonValidationErrors('field_type');
    }

    public function test_a_slot_the_host_does_not_declare_is_refused(): void
    {
        // Slots are the host's, not the tenant's: an arbitrary string would be
        // a place the panel has nowhere to draw.
        Sanctum::actingAs($this->adminWith(['custom-field-read', 'custom-field-manage']));

        $this->postJson('/api/admin/custom-fields', $this->payload(['slot' => 'card.nowhere']))
            ->assertStatus(422)->assertJsonValidationErrors('slot');
    }

    public function test_a_field_with_no_name_is_refused(): void
    {
        Sanctum::actingAs($this->adminWith(['custom-field-read', 'custom-field-manage']));

        $this->postJson('/api/admin/custom-fields', $this->payload(['labels' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('labels');
    }

    public function test_an_unsupported_locale_is_refused(): void
    {
        // Bounded by what the platform can render, never by the tenant's own
        // locales.enabled — enabled says what a tenant offers.
        Sanctum::actingAs($this->adminWith(['custom-field-read', 'custom-field-manage']));

        $this->postJson('/api/admin/custom-fields', $this->payload([
            'labels' => ['kl' => ['label' => 'Kalaallisut']],
        ]))->assertStatus(422);
    }

    public function test_an_uncompilable_pattern_is_refused_before_it_is_stored(): void
    {
        // Otherwise it would 422 every subsequent write on that host, and the
        // only way out would be editing the row by hand.
        Sanctum::actingAs($this->adminWith(['custom-field-read', 'custom-field-manage']));

        $this->postJson('/api/admin/custom-fields', $this->payload(['pattern' => '[unclosed']))
            ->assertStatus(422)->assertJsonValidationErrors('pattern');
    }

    public function test_a_switched_off_feature_answers_403_with_a_code_not_an_empty_list(): void
    {
        // The features.ai_agent lesson: a silent skip made the AI chat look
        // broken for three days.
        Setting::create([
            'key' => 'features.custom_fields_appointments',
            'value' => 'false',
            'type' => 'boolean',
            'group' => 'features',
            'label' => 'Custom Fields Appointments',
        ]);

        Sanctum::actingAs($this->adminWith(['custom-field-read', 'custom-field-manage']));

        $this->postJson('/api/admin/custom-fields', $this->payload())
            ->assertStatus(403)
            ->assertJsonPath('error', 'feature_disabled');
    }

    public function test_the_plan_limit_answers_402(): void
    {
        Setting::create([
            'key' => 'limits.max_custom_fields',
            'value' => '1',
            'type' => 'integer',
            'group' => 'limits',
            'label' => 'Max Custom Fields',
        ]);

        Sanctum::actingAs($this->adminWith(['custom-field-read', 'custom-field-manage']));

        $this->postJson('/api/admin/custom-fields', $this->payload())->assertStatus(202);

        // 402 Payment Required, from the global handler added in part 0 —
        // a cap is a billing answer, not a permission one.
        $this->postJson('/api/admin/custom-fields', $this->payload(['labels' => ['en' => ['label' => 'Second']]]))
            ->assertStatus(402);
    }
}
