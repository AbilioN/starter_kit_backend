<?php

namespace Tests\Feature\Template;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Template;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Tests\TenantTestCase;

class TemplateCrudTest extends TenantTestCase
{
    private Admin $adminWithAllPermissions;
    private Admin $adminWithNoPermissions;

    public function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenant();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);

        $superAdmin = Admin::where('is_super_admin', true)->first();

        $this->adminWithAllPermissions = Admin::factory()->create([
            'email' => 'templateadmin@test.com',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
        $role = Role::where('slug', 'admin')->first();
        $role->permissions()->sync(Permission::all()->pluck('id'));
        $this->adminWithAllPermissions->roles()->attach($role->id, [
            'assigned_at' => now(),
            'assigned_by' => $superAdmin->id,
        ]);

        $this->adminWithNoPermissions = Admin::factory()->create([
            'email' => 'noperm@test.com',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    private function token(Admin $admin): string
    {
        return $admin->createToken('t')->plainTextToken;
    }

    public function test_admin_can_create_a_text_email_template(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token($this->adminWithAllPermissions)])
            ->postJson('/api/admin/templates', [
                'name' => 'Welcome Email',
                'type' => 'text_email',
                'body_format' => 'text',
                'subject' => 'Welcome!',
                'body' => 'Hello {first_name}, welcome aboard.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Welcome Email')
            ->assertJsonPath('data.type', 'text_email');

        $this->assertDatabaseHas('templates', ['name' => 'Welcome Email', 'type' => 'text_email']);
    }

    public function test_type_and_body_format_mismatch_is_rejected(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token($this->adminWithAllPermissions)])
            ->postJson('/api/admin/templates', [
                'name' => 'Bad Combo',
                'type' => 'sms',
                'body_format' => 'html', // sms must be 'text'
                'body' => '<b>nope</b>',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('body_format');
    }

    public function test_pdf_type_accepts_either_html_or_positions_body_format(): void
    {
        $token = $this->token($this->adminWithAllPermissions);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/admin/templates', [
                'name' => 'No Background PDF', 'type' => 'pdf', 'body_format' => 'html', 'body' => '<p>Doc</p>',
            ])->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/admin/templates', [
                'name' => 'Underlay PDF', 'type' => 'pdf', 'body_format' => 'positions', 'body' => '[]',
            ])->assertStatus(201);
    }

    public function test_admin_without_template_create_permission_is_forbidden(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token($this->adminWithNoPermissions)])
            ->postJson('/api/admin/templates', [
                'name' => 'X', 'type' => 'text_email', 'body_format' => 'text', 'body' => 'x',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_list_and_filter_templates_by_type(): void
    {
        $token = $this->token($this->adminWithAllPermissions);
        Template::create(['name' => 'Email One', 'type' => 'text_email', 'body_format' => 'text', 'body' => 'a']);
        Template::create(['name' => 'SMS One', 'type' => 'sms', 'body_format' => 'text', 'body' => 'b']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/templates?type=sms');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('SMS One', $names);
        $this->assertNotContains('Email One', $names);
    }

    public function test_admin_can_update_a_template(): void
    {
        $token = $this->token($this->adminWithAllPermissions);
        $template = Template::create(['name' => 'Original', 'type' => 'text_email', 'body_format' => 'text', 'body' => 'a']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson("/api/admin/templates/{$template->id}", ['name' => 'Renamed']);

        $response->assertStatus(200)->assertJsonPath('data.name', 'Renamed');
    }

    public function test_admin_can_delete_a_template(): void
    {
        $token = $this->token($this->adminWithAllPermissions);
        $template = Template::create(['name' => 'To Delete', 'type' => 'text_email', 'body_format' => 'text', 'body' => 'a']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson("/api/admin/templates/{$template->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('templates', ['id' => $template->id]);
    }

    public function test_show_returns_404_for_an_unknown_template(): void
    {
        $token = $this->token($this->adminWithAllPermissions);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/templates/019fffff-0000-7000-8000-000000000000')
            ->assertStatus(404);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/admin/templates')->assertStatus(401);
    }
}
