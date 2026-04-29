<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $adminWithPermissions;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        $this->superAdmin = Admin::where('is_super_admin', true)->first();

        $this->adminWithPermissions = Admin::factory()->create([
            'name' => 'Setting Admin',
            'email' => 'settingadmin@test.com',
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $role = Role::where('slug', 'admin')->first();
        $role->permissions()->sync(Permission::all()->pluck('id'));
        $this->adminWithPermissions->roles()->attach($role->id, [
            'assigned_at' => now(),
            'assigned_by' => $this->superAdmin->id,
        ]);
    }

    /** @test */
    public function super_admin_can_list_settings(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/admin/settings');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [['id', 'key', 'value', 'type', 'group', 'label', 'is_public']]]);
    }

    /** @test */
    public function admin_with_permission_can_list_settings(): void
    {
        $token = $this->adminWithPermissions->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/admin/settings');

        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    /** @test */
    public function admin_can_filter_settings_by_group(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/admin/settings?group=features');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $groups = collect($response->json('data'))->pluck('group')->unique()->values()->all();
        $this->assertEquals(['features'], $groups);
    }

    /** @test */
    public function super_admin_can_update_setting(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson('/api/admin/settings/app.name', ['value' => 'My App']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.key', 'app.name')
            ->assertJsonPath('data.value', 'My App');
    }

    /** @test */
    public function update_nonexistent_setting_returns_404(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson('/api/admin/settings/does.not.exist', ['value' => 'test']);

        $response->assertStatus(404)->assertJsonPath('success', false);
    }

    /** @test */
    public function public_settings_endpoint_requires_no_auth(): void
    {
        $response = $this->getJson('/api/settings/public');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $keys = collect($response->json('data'))->pluck('is_public')->unique()->values()->all();
        $this->assertEquals([true], $keys);
    }

    /** @test */
    public function unauthorized_admin_cannot_update_settings(): void
    {
        $noPermAdmin = Admin::factory()->create(['is_active' => true, 'is_super_admin' => false]);
        $token = $noPermAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson('/api/admin/settings/app.name', ['value' => 'hack']);

        $response->assertStatus(403);
    }
}
