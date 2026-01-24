<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Role;
use App\Models\Permission;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $adminWithRoleManage;
    private Admin $adminWithoutRoleManage;

    public function setUp(): void
    {
        parent::setUp();
        
        // Seed the database
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
        
        $this->superAdmin = Admin::where('is_super_admin', true)->first();
        
        // Create admin with role-manage
        $this->adminWithRoleManage = Admin::factory()->create([
            'name' => 'Admin With Role Manage',
            'email' => 'admin_with_role_manage@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);
        
        // Create admin without role-manage
        $this->adminWithoutRoleManage = Admin::factory()->create([
            'name' => 'Admin Without Role Manage',
            'email' => 'admin_without_role_manage@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);
        
        // Assign role-manage to adminWithRoleManage
        $adminRole = Role::where('slug', 'admin')->first();
        $roleManagePermission = Permission::where('slug', 'role-manage')->first();
        
        if ($roleManagePermission) {
            $adminRole->permissions()->syncWithoutDetaching([$roleManagePermission->id]);
            $this->adminWithRoleManage->roles()->attach($adminRole->id, [
                'assigned_at' => now(),
                'assigned_by' => $this->superAdmin->id
            ]);
        }
    }

    /**
     * @test
     */
    public function super_admin_can_list_all_permissions(): void
    {
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->get('/api/admin/permissions');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'resource',
                        'action'
                    ]
                ]
            ]);
        
        $permissions = $response->json('data');
        $this->assertNotEmpty($permissions);
        
        // Verify it contains the expected permission structure
        $firstPermission = $permissions[0];
        $this->assertArrayHasKey('id', $firstPermission);
        $this->assertArrayHasKey('name', $firstPermission);
        $this->assertArrayHasKey('slug', $firstPermission);
        $this->assertArrayHasKey('description', $firstPermission);
    }

    /**
     * @test
     */
    public function admin_with_role_manage_can_list_all_permissions(): void
    {
        $token = $this->adminWithRoleManage->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->get('/api/admin/permissions');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'resource',
                        'action'
                    ]
                ]
            ]);
    }

    /**
     * @test
     */
    public function admin_without_role_manage_cannot_list_permissions(): void
    {
        $token = $this->adminWithoutRoleManage->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->get('/api/admin/permissions');
        
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Admin ' . $this->adminWithoutRoleManage->id . ' does not have permission to perform this action. Required permission: role-manage'
            ]);
    }

    /**
     * @test
     */
    public function unauthenticated_user_cannot_access_permissions(): void
    {
        $response = $this->getJson('/api/admin/permissions');
        
        $response->assertStatus(401);
    }
}
