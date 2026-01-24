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

class AdminsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $adminWithAllPermissions;

    public function setUp(): void
    {
        parent::setUp();
        
        // Seed the database
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
        
        $this->superAdmin = Admin::where('is_super_admin', true)->first();
        
        // Create admin with ALL permissions
        $this->adminWithAllPermissions = Admin::factory()->create([
            'name' => 'Admin With All Permissions',
            'email' => 'allperms@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);
        
        // Assign role with ALL permissions
        $adminRole = Role::where('slug', 'admin')->first();
        $allPermissions = Permission::all();
        $adminRole->permissions()->sync($allPermissions->pluck('id'));
        $this->adminWithAllPermissions->roles()->attach($adminRole->id, [
            'assigned_at' => now(),
            'assigned_by' => $this->superAdmin->id
        ]);
    }

    /** @test */
    public function super_admin_can_list_all_admins(): void
    {
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'is_active',
                        'is_super_admin',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'pagination' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                    'from',
                    'to'
                ]
            ]);
        $this->assertNotEmpty($response->json('data'));
    }

    /** @test */
    public function admin_with_admin_read_can_list_all_admins(): void
    {
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'is_active',
                        'is_super_admin'
                    ]
                ],
                'pagination' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page'
                ]
            ]);
    }

    /** @test */
    public function admin_without_admin_read_cannot_list_admins(): void
    {
        // Remove admin-read permission
        $adminRole = Role::where('slug', 'admin')->first();
        $adminReadPermission = Permission::where('slug', 'admin-read')->first();
        $currentPermissions = $adminRole->permissions()->pluck('permissions.id')->toArray();
        $remainingPermissions = array_diff($currentPermissions, [$adminReadPermission->id]);
        $adminRole->permissions()->sync($remainingPermissions);
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins');
        
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Admin ' . $this->adminWithAllPermissions->id . ' does not have permission to perform this action. Required permission: admin-read'
            ]);
    }

    /** @test */
    public function super_admin_can_create_admin(): void
    {
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $adminData = [
            'name' => 'New Admin',
            'email' => 'newadmin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true
        ];
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/admin/admins', $adminData);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'is_active',
                    'is_super_admin'
                ]
            ]);
        
        $this->assertDatabaseHas('admins', [
            'email' => 'newadmin@test.com',
            'name' => 'New Admin'
        ]);
    }

    /** @test */
    public function admin_with_admin_create_can_create_admin(): void
    {
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        $adminData = [
            'name' => 'Another Admin',
            'email' => 'anotheradmin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true
        ];
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/admin/admins', $adminData);
        
        $response->assertStatus(201);
        
        $this->assertDatabaseHas('admins', [
            'email' => 'anotheradmin@test.com'
        ]);
    }

    /** @test */
    public function admin_without_admin_create_cannot_create_admin(): void
    {
        // Remove admin-create permission
        $adminRole = Role::where('slug', 'admin')->first();
        $adminCreatePermission = Permission::where('slug', 'admin-create')->first();
        $currentPermissions = $adminRole->permissions()->pluck('permissions.id')->toArray();
        $remainingPermissions = array_diff($currentPermissions, [$adminCreatePermission->id]);
        $adminRole->permissions()->sync($remainingPermissions);
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        $adminData = [
            'name' => 'Blocked Admin',
            'email' => 'blocked@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true
        ];
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/admin/admins', $adminData);
        
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Admin ' . $this->adminWithAllPermissions->id . ' does not have permission to perform this action. Required permission: admin-create'
            ]);
    }

    /** @test */
    public function super_admin_can_update_admin(): void
    {
        $adminToUpdate = Admin::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@test.com',
            'is_active' => true
        ]);
        
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $updateData = [
            'id' => $adminToUpdate->id,
            'name' => 'Updated Name',
            'email' => 'updated@test.com',
            'is_active' => false
        ];
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->putJson('/api/admin/admins', $updateData);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'is_active'
                ]
            ]);
        
        $this->assertDatabaseHas('admins', [
            'id' => $adminToUpdate->id,
            'name' => 'Updated Name',
            'email' => 'updated@test.com',
            'is_active' => false
        ]);
    }

    /** @test */
    public function admin_with_admin_update_can_update_admin(): void
    {
        $adminToUpdate = Admin::factory()->create([
            'name' => 'Test Admin',
            'email' => 'test@test.com'
        ]);
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        $updateData = [
            'id' => $adminToUpdate->id,
            'name' => 'Modified Name'
        ];
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->putJson('/api/admin/admins', $updateData);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('admins', [
            'id' => $adminToUpdate->id,
            'name' => 'Modified Name'
        ]);
    }

    /** @test */
    public function admin_without_admin_update_cannot_update_admin(): void
    {
        $adminToUpdate = Admin::factory()->create([
            'name' => 'Test Admin',
            'email' => 'test@test.com'
        ]);
        
        // Remove admin-update permission
        $adminRole = Role::where('slug', 'admin')->first();
        $adminUpdatePermission = Permission::where('slug', 'admin-update')->first();
        $currentPermissions = $adminRole->permissions()->pluck('permissions.id')->toArray();
        $remainingPermissions = array_diff($currentPermissions, [$adminUpdatePermission->id]);
        $adminRole->permissions()->sync($remainingPermissions);
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        $updateData = [
            'id' => $adminToUpdate->id,
            'name' => 'Should Not Update'
        ];
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->putJson('/api/admin/admins', $updateData);
        
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Admin ' . $this->adminWithAllPermissions->id . ' does not have permission to perform this action. Required permission: admin-update'
            ]);
    }

    /** @test */
    public function super_admin_can_delete_admin(): void
    {
        $adminToDelete = Admin::factory()->create([
            'name' => 'To Delete',
            'email' => 'delete@test.com'
        ]);
        
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson('/api/admin/admins', ['id' => $adminToDelete->id]);
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $this->assertDatabaseMissing('admins', [
            'id' => $adminToDelete->id
        ]);
    }

    /** @test */
    public function admin_with_admin_delete_can_delete_admin(): void
    {
        $adminToDelete = Admin::factory()->create([
            'name' => 'To Delete',
            'email' => 'delete2@test.com'
        ]);
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson('/api/admin/admins', ['id' => $adminToDelete->id]);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('admins', [
            'id' => $adminToDelete->id
        ]);
    }

    /** @test */
    public function admin_without_admin_delete_cannot_delete_admin(): void
    {
        $adminToDelete = Admin::factory()->create([
            'name' => 'Should Not Delete',
            'email' => 'nodelete@test.com'
        ]);
        
        // Remove admin-delete permission
        $adminRole = Role::where('slug', 'admin')->first();
        $adminDeletePermission = Permission::where('slug', 'admin-delete')->first();
        $currentPermissions = $adminRole->permissions()->pluck('permissions.id')->toArray();
        $remainingPermissions = array_diff($currentPermissions, [$adminDeletePermission->id]);
        $adminRole->permissions()->sync($remainingPermissions);
        
        $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson('/api/admin/admins', ['id' => $adminToDelete->id]);
        
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Admin ' . $this->adminWithAllPermissions->id . ' does not have permission to perform this action. Required permission: admin-delete'
            ]);
        
        $this->assertDatabaseHas('admins', [
            'id' => $adminToDelete->id
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_admins(): void
    {
        $response = $this->getJson('/api/admin/admins');
        
        $response->assertStatus(401);
    }

    /** @test */
    public function cannot_create_admin_with_duplicate_email(): void
    {
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $adminData = [
            'name' => 'Duplicate Email Admin',
            'email' => $this->superAdmin->email, // Email já existe
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true
        ];
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/admin/admins', $adminData);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function cannot_create_admin_without_required_fields(): void
    {
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $adminData = [
            // Faltando campos obrigatórios
        ];
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/admin/admins', $adminData);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /** @test */
    public function can_create_admin_with_role(): void
    {
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        // Obter uma role existente
        $role = Role::where('slug', 'admin')->first();
        
        $adminData = [
            'name' => 'Admin With Role',
            'email' => 'adminwithrole@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true,
            'role_id' => $role->id
        ];
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/admin/admins', $adminData);
        
        $response->assertStatus(201);
        
        // Verificar que o admin foi criado
        $this->assertDatabaseHas('admins', [
            'email' => 'adminwithrole@test.com'
        ]);
        
        // Verificar que a role foi atribuída
        $newAdmin = Admin::where('email', 'adminwithrole@test.com')->first();
        $this->assertDatabaseHas('admin_roles', [
            'admin_id' => $newAdmin->id,
            'role_id' => $role->id,
            'assigned_by' => $this->superAdmin->id
        ]);
    }

    /** @test */
    public function cannot_create_admin_with_invalid_role(): void
    {
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $adminData = [
            'name' => 'Admin With Invalid Role',
            'email' => 'invalidrole@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true,
            'role_id' => 99999 // Role que não existe
        ];
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/admin/admins', $adminData);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    /** @test */
    public function can_paginate_admins_with_custom_per_page(): void
    {
        // Create 30 additional admins
        Admin::factory()->count(30)->create();
        
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins?page=1&per_page=10');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                    'from',
                    'to'
                ]
            ])
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.current_page', 1);
        
        // Verificar que retornou exatamente 10 itens
        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function can_navigate_to_second_page(): void
    {
        // Create 30 additional admins
        Admin::factory()->count(30)->create();
        
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins?page=2&per_page=10');
        
        $response->assertStatus(200)
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.per_page', 10);
        
        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function can_search_admins_by_name(): void
    {
        Admin::factory()->create(['name' => 'John Doe', 'email' => 'john@test.com']);
        Admin::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@test.com']);
        Admin::factory()->count(10)->create();
        
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins?search=John');
        
        $response->assertStatus(200);
        
        // Verificar que retornou apenas resultados com "John" no nome
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        
        $foundJohn = false;
        foreach ($data as $admin) {
            if (str_contains(strtolower($admin['name']), 'john')) {
                $foundJohn = true;
                break;
            }
        }
        $this->assertTrue($foundJohn, 'Should find admin with "John" in name');
    }

    /** @test */
    public function can_search_admins_by_email(): void
    {
        Admin::factory()->create(['name' => 'Test Admin', 'email' => 'unique@example.com']);
        Admin::factory()->count(10)->create();
        
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins?search=unique@example.com');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        
        // Verificar que encontrou o admin com o email específico
        $foundAdmin = collect($data)->first(fn($admin) => $admin['email'] === 'unique@example.com');
        $this->assertNotNull($foundAdmin);
    }

    /** @test */
    public function can_filter_admins_by_active_status(): void
    {
        Admin::factory()->count(5)->create(['is_active' => true]);
        Admin::factory()->count(3)->create(['is_active' => false]);
        
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        // Filter active admins
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins?is_active=true');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        
        // Verificar que todos são ativos
        foreach ($data as $admin) {
            $this->assertTrue($admin['is_active'], 'All admins should be active');
        }
    }

    /** @test */
    public function can_filter_admins_by_inactive_status(): void
    {
        Admin::factory()->count(5)->create(['is_active' => true]);
        Admin::factory()->count(3)->create(['is_active' => false]);
        
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        // Filter inactive admins
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins?is_active=false');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        
        // Verificar que todos são inativos
        foreach ($data as $admin) {
            $this->assertFalse($admin['is_active'], 'All admins should be inactive');
        }
    }

    /** @test */
    public function can_combine_search_and_filters(): void
    {
        Admin::factory()->create([
            'name' => 'Active John',
            'email' => 'activejohn@test.com',
            'is_active' => true
        ]);
        Admin::factory()->create([
            'name' => 'Inactive John',
            'email' => 'inactivejohn@test.com',
            'is_active' => false
        ]);
        Admin::factory()->count(10)->create(['is_active' => true]);
        
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins?search=John&is_active=true');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        
        // Verificar que encontrou apenas "Active John"
        foreach ($data as $admin) {
            $this->assertTrue($admin['is_active']);
            $this->assertTrue(
                str_contains(strtolower($admin['name']), 'john') || 
                str_contains(strtolower($admin['email']), 'john')
            );
        }
    }

    /** @test */
    public function pagination_respects_max_per_page_limit(): void
    {
        Admin::factory()->count(50)->create();
        
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        // Try to request 200 per page (should be limited to 100)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins?per_page=200');
        
        $response->assertStatus(200)
            ->assertJsonPath('pagination.per_page', 100); // Should be limited to 100
    }

    /** @test */
    public function pagination_respects_min_per_page_limit(): void
    {
        Admin::factory()->count(10)->create();
        
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        
        // Try to request 0 per page (should default to 1)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins?per_page=0');
        
        $response->assertStatus(200)
            ->assertJsonPath('pagination.per_page', 1); // Should be limited to minimum 1
    }

    /** @test */
    public function default_pagination_is_15_items(): void
    {
        Admin::factory()->count(30)->create();
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;
        // Request without specifying per_page
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/admins');
        
        $response->assertStatus(200)
            ->assertJsonPath('pagination.per_page', 15); // Default should be 15
    }
}
