<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{

    use RefreshDatabase;

    private Admin $sudoAdminModel;
    private Admin $adminWithAllPermissions;

    public function setUp(): void
    {  
        parent::setUp();
        // Seed the database in the correct order
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
        
        $this->sudoAdminModel = Admin::where('is_super_admin', true)->first();
        // Create admin with ALL permissions using factory
        $this->adminWithAllPermissions = Admin::factory()->create([
            'name' => 'Admin With All Permissions',
            'email' => 'allperms@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);
        // Assign role with ALL permissions to adminWithAllPermissions
        $adminRole = Role::where('slug', 'admin')->first();
        $allPermissions = Permission::all();
        $adminRole->permissions()->sync($allPermissions->pluck('id'));
        $this->adminWithAllPermissions->roles()->attach($adminRole->id, [
            'assigned_at' => now(),
            'assigned_by' => $this->sudoAdminModel->id
        ]);
        // Assert that the seeder worked
        $this->assertNotNull($this->sudoAdminModel, 'Super admin should be created by AdminSeeder');
        $this->assertNotNull($this->adminWithAllPermissions, 'Admin with all permissions should be created');
    }

    /**
     * @test
     */
    public function an_admin_can_list_roles(): void
    {
        // Arrange - Get admin token from admin with all permissions
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->get('/api/admin/roles');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'is_active',
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    /**
     * @test
     */
    public function an_admin_can_create_a_role(): void
    {
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->post('/api/admin/role/create', ['name' => 'Test Role', 'description' => 'Test Description']);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['role' => ['id', 'name', 'slug', 'description', 'is_active', 'created_at', 'updated_at']]
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Test Role',
            'slug' => 'test-role',
            'description' => 'Test Description',
            'is_active' => true
            ]);
    }
    /**
     * @test
     */
     public function an_admin_can_create_a_role_with_permissions(): void
     {
        // Arrange
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        $roleData = [
            'name' => 'Test Role with Permissions', 
            'description' => 'Test Description with permissions',
            'permissions' => [1, 2, 3]
        ];
        
        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->post('/api/admin/role/create', $roleData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'role' => [
                        'id', 
                        'name', 
                        'slug', 
                        'description', 
                        'is_active', 
                        'created_at', 
                        'updated_at', 
                        'permissions' => [
                            '*' => [
                                'id',
                                'slug',
                                'name',
                                'description',
                                'is_active',
                                'resource',
                                'action'
                            ]
                        ]
                    ]
                ]
            ]);

        // Assert specific values
        $responseData = $response->json('data.role');
        
        $this->assertEquals('Test Role with Permissions', $responseData['name']);
        $this->assertEquals('test-role-with-permissions', $responseData['slug']);
        $this->assertEquals('Test Description with permissions', $responseData['description']);
        $this->assertTrue($responseData['is_active']);
        
        // Assert permissions were attached correctly
        $this->assertCount(3, $responseData['permissions']);
        $this->assertContains(1, array_column($responseData['permissions'], 'id'));
        $this->assertContains(2, array_column($responseData['permissions'], 'id'));
        $this->assertContains(3, array_column($responseData['permissions'], 'id'));
        
        // Assert role was actually created in database
        $this->assertDatabaseHas('roles', [
            'name' => 'Test Role with Permissions',
            'slug' => 'test-role-with-permissions',
            'description' => 'Test Description with permissions',
            'is_active' => true
        ]);
        
        // Assert permissions were attached in pivot table
        $roleId = $responseData['id'];
        $this->assertDatabaseHas('role_permissions', ['role_id' => $roleId, 'permission_id' => 1]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $roleId, 'permission_id' => 2]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $roleId, 'permission_id' => 3]);
     }

    /**
     * @test
     */
    public function admin_cannot_create_role_without_create_permission(): void
    {
        $adminRole = $this->adminWithAllPermissions->roles()->first();
        $roleCreatePermission = Permission::where('slug', 'role-create')->first();
        $currentPermissions = $adminRole->permissions()->pluck('permission_id')->toArray();
        $remainingPermissions = array_diff($currentPermissions, [$roleCreatePermission->id]);
        $adminRole->permissions()->sync($remainingPermissions);
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->post('/api/admin/role/create', [
            'name' => 'Test Role',
            'description' => 'Test Description'
        ]);
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Admin ' . $this->adminWithAllPermissions->id . ' does not have permission to perform this action. Required permission: role-create'
            ]);
    }

    /**
     * @test
     */
    public function admin_cannot_list_roles_without_read_permission(): void
    {
        $adminRole = $this->adminWithAllPermissions->roles()->first();
        $roleReadPermission = Permission::where('slug', 'role-read')->first();
        $currentPermissions = $adminRole->permissions()->pluck('permission_id')->toArray();
        $remainingPermissions = array_diff($currentPermissions, [$roleReadPermission->id]);
        $adminRole->permissions()->sync($remainingPermissions);
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        // Test role listing (should fail because we removed role-read permission)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->get('/api/admin/roles');
        
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Admin ' . $this->adminWithAllPermissions->id . ' does not have permission to perform this action. Required permission: role-read'
            ]);
    }

     /**
      * @test
      */
      public function an_admin_cannot_update_a_role_without_permission(): void
      {
        // Remove role-update permission from admin's role BEFORE making the request
        $adminRole = $this->adminWithAllPermissions->roles()->first();
        $roleUpdatePermission = Permission::where('slug', 'role-update')->first();
        $currentPermissions = $adminRole->permissions()->pluck('permission_id')->toArray();
        $remainingPermissions = array_diff($currentPermissions, [$roleUpdatePermission->id]);
        $adminRole->permissions()->sync($remainingPermissions);
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        // Test role update (should fail because we removed role-update permission)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->put('/api/admin/role/update', ['id' => 1, 'name' => 'Test Role', 'description' => 'Test Description']);
        $response->assertStatus(403)
            ->assertJson(['error' => 'Admin ' . $this->adminWithAllPermissions->id . ' does not have permission to perform this action. Required permission: role-update']);
     
    }

    /**
     * @test
     */
    public function an_admin_can_update_a_role_when_has_update_permission(): void
    {
        // Create a test role specifically for updating
        $testRole = Role::create([
            'slug' => 'test-update-role',
            'name' => 'Original Role Name',
            'description' => 'Original Description',
            'is_active' => true,
        ]);
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->put('/api/admin/role/update', [
            'id' => $testRole->id, 
            'name' => 'Updated Role Name', 
            'description' => 'Updated Description'
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['role' => ['id', 'name', 'slug', 'description', 'is_active', 'created_at', 'updated_at']]]);
            
        $this->assertDatabaseHas('roles', [
            'id' => $testRole->id,
            'name' => 'Updated Role Name',
            'slug' => 'updated-role-name',
            'description' => 'Updated Description',
            'is_active' => true
        ]);
    }

    /**
     * @test
     */
    public function an_admin_cannot_update_a_role_when_does_not_have_update_permission(): void
    {
        // Create a test role specifically for this test
        $testRole = Role::create([
            'slug' => 'test-no-update-role',
            'name' => 'Original Role Name',
            'description' => 'Original Description',
            'is_active' => true,
        ]);
        
        // Remove role-update permission from admin's role
        $adminRole = $this->adminWithAllPermissions->roles()->first();
        $roleUpdatePermission = Permission::where('slug', 'role-update')->first();
        $currentPermissions = $adminRole->permissions()->pluck('permission_id')->toArray();
        $remainingPermissions = array_diff($currentPermissions, [$roleUpdatePermission->id]);
        $adminRole->permissions()->sync($remainingPermissions);
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->put('/api/admin/role/update', [
            'id' => $testRole->id, 
            'name' => 'Updated Role Name', 
            'description' => 'Updated Description'
        ]);
        
        $response->assertStatus(403)
            ->assertJson(['error' => 'Admin ' . $this->adminWithAllPermissions->id . ' does not have permission to perform this action. Required permission: role-update']);
            
        // Verify role was NOT updated in database
        $this->assertDatabaseMissing('roles', [
            'id' => $testRole->id,
            'name' => 'Updated Role Name',
            'slug' => 'updated-role-name',
            'description' => 'Updated Description'
        ]);
    }

    /**
     * @test
     */
    public function an_admin_can_delete_a_role_when_has_delete_permission(): void
    {
        $testRole = Role::create([
            'slug' => 'test-delete-role',
            'name' => 'Test Delete Role',
            'description' => 'Role for testing deletion',
            'is_active' => true,
        ]);
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->post('/api/admin/role/delete', ['id' => $testRole->id]); 
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseMissing('roles', [
            'id' => $testRole->id
        ]);

    }

    /**
     * @test
     */
    public function an_admin_cannot_delete_a_role_without_delete_permission(): void
    {
        $testRole = Role::create([
            'slug' => 'test-no-delete-role',
            'name' => 'Test No Delete Role',
            'description' => 'Role for testing no delete permission',
            'is_active' => true,
        ]);
        $adminRole = $this->adminWithAllPermissions->roles()->first();
        $roleDeletePermission = Permission::where('slug', 'role-delete')->first();
        $currentPermissions = $adminRole->permissions()->pluck('permission_id')->toArray();
        $remainingPermissions = array_diff($currentPermissions, [$roleDeletePermission->id]);
        $adminRole->permissions()->sync($remainingPermissions);
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->post('/api/admin/role/delete', ['id' => $testRole->id]);
        
        $response->assertStatus(403)
            ->assertJson(['error' => 'Admin ' . $this->adminWithAllPermissions->id . ' does not have permission to perform this action. Required permission: role-delete']);
        $this->assertDatabaseHas('roles', [
            'id' => $testRole->id
        ]);
    }



    /**
     * @test
     */
    public function an_admin_cannot_change_permissions_to_a_role_without_manage_permission(): void
    {
        // Create a test role specifically for this test
        $testRole = Role::create([
            'slug' => 'test-no-manage-role',
            'name' => 'Test No Manage Role',
            'description' => 'Role for testing no manage permission',
            'is_active' => true,
        ]);
        
        // Remove role-manage permission from admin's role
        $adminRole = $this->adminWithAllPermissions->roles()->first();
        $roleManagePermission = Permission::where('slug', 'role-manage')->first();
        $currentPermissions = $adminRole->permissions()->pluck('permission_id')->toArray();
        $remainingPermissions = array_diff($currentPermissions, [$roleManagePermission->id]);
        $adminRole->permissions()->sync($remainingPermissions);
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->post('/api/admin/role/update-permissions', [
            'id' => $testRole->id, 
            'permissions' => [1, 2, 3]
        ]);
        
        $response->assertStatus(403)
            ->assertJson(['error' => 'Admin ' . $this->adminWithAllPermissions->id . ' does not have permission to perform this action. Required permission: role-manage']);
    }

    /**
     * @test
     */
    public function an_admin_can_update_permissions_when_has_manage_permission(): void
    {
        // Create a test role specifically for this test
        $testRole = Role::create([
            'slug' => 'test-update-perms-role',
            'name' => 'Test Update Perms Role',
            'description' => 'Role for testing permission updates',
            'is_active' => true,
        ]);
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        // Get some permissions to assign
        $permissions = Permission::limit(3)->get();
        $permissionIds = $permissions->pluck('id')->toArray();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->post('/api/admin/role/update-permissions', [
            'id' => $testRole->id, 
            'permissions' => $permissionIds
        ]);
        
        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['role' => ['id', 'name', 'permissions']]]);
            
        // Verify permissions were updated in database
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $testRole->id,
            'permission_id' => $permissionIds[0]
        ]);
    }

    /**
     * @test
     */
    public function an_admin_cannot_update_permissions_with_invalid_permission_ids(): void
    {
        // Create a test role specifically for this test
        $testRole = Role::create([
            'slug' => 'test-invalid-perms-role',
            'name' => 'Test Invalid Perms Role',
            'description' => 'Role for testing invalid permission IDs',
            'is_active' => true,
        ]);
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        // Try to assign permissions with invalid IDs
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->post('/api/admin/role/update-permissions', [
            'id' => $testRole->id, 
            'permissions' => [999, 1000, 1001] // IDs que não existem
        ]);
        
        $response->assertStatus(500)
            ->assertJson(['error' => 'Some permissions do not exist. Missing permission IDs: 999, 1000, 1001']);
    }

    /**
     * @test
     */
    public function an_admin_can_remove_all_permissions_from_role(): void
    {
        // Create a test role with some permissions
        $testRole = Role::create([
            'slug' => 'test-remove-all-perms-role',
            'name' => 'Test Remove All Perms Role',
            'description' => 'Role for testing permission removal',
            'is_active' => true,
        ]);
        
        // First assign some permissions
        $permissions = Permission::limit(2)->get();
        $testRole->permissions()->sync($permissions->pluck('id'));
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        // Now remove all permissions by sending empty array
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->post('/api/admin/role/update-permissions', [
            'id' => $testRole->id, 
            'permissions' => [] // Array vazio remove todas as permissions
        ]);
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
            
        // Verify no permissions are assigned
        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $testRole->id
        ]);
    }
}
