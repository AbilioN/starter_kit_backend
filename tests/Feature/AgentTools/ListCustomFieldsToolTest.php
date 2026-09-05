<?php

namespace Tests\Feature\AgentTools;

use App\Application\AgentTools\ListCustomFieldsTool;
use App\Application\UseCases\CustomField\CreateFieldDefinitionUseCase;
use App\Domain\AgentTools\AgentToolContext;
use App\Domain\CustomFields\CustomFieldStates;
use App\Infrastructure\CustomFields\CatalogueLoader;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * What the assistant may say about the fields a workspace invented.
 *
 * The load-bearing test is the third one. Listing field NAMES sounds harmless
 * until the field is called "Salary band" — the study's third pitfall is a
 * permission enforced in one renderer and forgotten in the next, and an AI
 * answer is the newest renderer. The tool reads through the projector for
 * exactly that reason.
 */
class ListCustomFieldsToolTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('cftool');

        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

        // The reconciler is MySQL-only, so the column is made by hand here.
        // What this suite asserts is what the tool says, not what the ALTER did.
        Schema::connection('tenant')->table('appointments', function ($table) {
            $table->string('cf_1', 190)->nullable();
        });

        CatalogueLoader::forget();
    }

    private function defineField(string $label, array $roleRules = [], bool $live = true): void
    {
        $definition = app(CreateFieldDefinitionUseCase::class)->execute(
            hostKey: 'appointments',
            fieldType: 'text',
            labels: ['en' => ['label' => $label]],
            isFilterable: true,
            roleRules: $roleRules,
            presentation: ['slot' => 'card.badges'],
        );

        if ($live) {
            $definition->update(['state' => CustomFieldStates::LIVE]);
        }

        CatalogueLoader::forget();
    }

    private function admin(bool $superAdmin, array $roleSlugs = []): Admin
    {
        $admin = Admin::factory()->create(['is_super_admin' => $superAdmin, 'is_active' => true]);

        foreach ($roleSlugs as $slug) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucfirst($slug), 'description' => $slug, 'is_active' => true],
            );
            $role->permissions()->syncWithoutDetaching(Permission::where('slug', 'custom-field-read')->pluck('id'));
            $admin->roles()->attach($role->id, [
                'assigned_at' => now(), 'assigned_by' => $admin->id, 'is_active' => true,
            ]);
        }

        return $admin->refresh();
    }

    /** @return array<int, array<string, mixed>> the rows the handler returned */
    private function runTool(Admin $actor, array $arguments = []): array
    {
        // The identity arrives in the CONTEXT, the way the executor supplies
        // it — never from Auth::, which does not exist on this path. Getting
        // that wrong fails open: the factory would hand back the system viewer
        // and describe every hidden field.
        $context = new AgentToolContext(
            tenantId: 'tenant-under-test',
            actorId: $actor->id,
            actorType: 'admin',
            chatId: 'chat-1',
            requestId: 'req-1',
            impersonatedBy: null,
            maxRows: 50,
        );

        // AgentToolResult keeps truncation in the type rather than flattening
        // to an array — a handler cannot return a bare list on purpose.
        return app(ListCustomFieldsTool::class)->execute($arguments, $context)->value;
    }

    public function test_it_describes_the_fields_this_workspace_defined(): void
    {
        $this->defineField('Contract number');

        $rows = $this->runTool($this->admin(superAdmin: true));
        $row = collect($rows)->firstWhere('name', 'Contract number');

        $this->assertNotNull($row, 'The tool must describe a field the workspace defined.');
        $this->assertSame('appointments', $row['entity']);
        $this->assertSame('text', $row['type']);
        $this->assertTrue($row['filterable']);
        $this->assertTrue($row['ready']);
    }

    public function test_a_field_whose_column_does_not_exist_yet_is_not_reported_as_ready(): void
    {
        // "We have a contract number field" is misleading while the column is
        // still being created — the answer has to carry the difference.
        $this->defineField('Still pending', live: false);

        $rows = $this->runTool($this->admin(superAdmin: true));

        // A pending field is not readable at all, so the honest answer is that
        // it is absent rather than present-but-not-ready.
        $this->assertNull(collect($rows)->firstWhere('name', 'Still pending'));
    }

    public function test_it_never_names_a_field_hidden_from_the_actors_roles(): void
    {
        $support = $this->admin(superAdmin: false, roleSlugs: ['support']);
        $roleId = Role::where('slug', 'support')->value('id');

        $this->defineField('Salary band', roleRules: ['hidden' => [$roleId]]);
        $this->defineField('Contract number');

        $rows = $this->runTool($support);

        $this->assertNull(
            collect($rows)->firstWhere('name', 'Salary band'),
            'A field hidden from this admin must not be described to them by the assistant either.',
        );

        // And the tool is not simply returning nothing, which would make the
        // assertion above pass for the wrong reason.
        $this->assertNotNull(collect($rows)->firstWhere('name', 'Contract number'));
    }

    public function test_the_entity_argument_narrows_the_answer(): void
    {
        $this->defineField('Contract number');

        $rows = $this->runTool($this->admin(superAdmin: true), ['entity' => 'users']);

        $this->assertSame([], $rows);
    }

    public function test_it_reads_the_permission_and_never_the_one_that_runs_ddl(): void
    {
        // `custom-field-manage` runs ALTER TABLE. No tool should hold it, and
        // the executor is what enforces this — the assertion is on the
        // declaration, which is the thing a reviewer would skim past.
        $this->assertSame('custom-field-read', app(ListCustomFieldsTool::class)->permission());
        $this->assertFalse(app(ListCustomFieldsTool::class)->isMutating());
    }

    public function test_its_schema_survives_the_wire(): void
    {
        // A tool whose parameters serialise as [] rather than an object fails
        // the WHOLE turn, for every other tool too — the my_profile bug of
        // 2026-08-29. This one has a property, so it is safe; the enum is the
        // part worth pinning, because it comes from the host registry.
        $parameters = app(ListCustomFieldsTool::class)->parameters();

        $this->assertSame('object', $parameters['type']);
        $this->assertContains('appointments', $parameters['properties']['entity']['enum']);
    }
}
