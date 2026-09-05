<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\TenantTestCase;

class UserManagementTest extends TenantTestCase
{
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenant();

        // Roles and permissions exist so the slugs below are real rows.
        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

        // Deliberately NOT a super admin.
        //
        // Until 2026-09-05 UserController::index() and show() ran no
        // authorization at all — `user-read` was a slug nothing checked, so any
        // authenticated admin could list and read every end user regardless of
        // role. These tests passed BECAUSE of that gap. Granting the
        // permissions explicitly is what makes them assert something.
        $admin = Admin::factory()->create([
            'email' => 'admin3@dashboard.com',
            'password' => bcrypt('password123'),
            'is_super_admin' => false,
        ]);

        $this->grant($admin, ['user-read', 'user-create', 'user-update', 'user-delete']);

        $loginResponse = $this->postJson('/api/admin/login', [
            'email' => 'admin3@dashboard.com',
            'password' => 'password123'
        ]);

        $this->adminToken = $loginResponse->json('token');
    }

    /** @param array<int, string> $permissionSlugs */
    private function grant(Admin $admin, array $permissionSlugs): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'user-manager'],
            ['name' => 'User manager', 'description' => 'Manages end users', 'is_active' => true],
        );

        $role->permissions()->syncWithoutDetaching(
            Permission::whereIn('slug', $permissionSlugs)->pluck('id'),
        );

        // admin_roles is an audit-shaped pivot: assigned_at and assigned_by are
        // NOT NULL.
        $admin->roles()->syncWithoutDetaching([
            $role->id => ['assigned_at' => now(), 'assigned_by' => $admin->id, 'is_active' => true],
        ]);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->adminToken];
    }

    public function test_admin_can_list_users_with_pagination()
    {
        // Arrange
        User::factory()->count(10)->create();

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/admin/users?page=1&per_page=5');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'users' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'email_verified_at',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'from',
                    'to'
                ]
            ]);

        $this->assertCount(5, $response->json('users'));
        $this->assertEquals(1, $response->json('pagination.current_page'));
        $this->assertEquals(5, $response->json('pagination.per_page'));
        $this->assertEquals(10, $response->json('pagination.total'));
    }

    public function test_admin_can_list_users_with_default_pagination()
    {
        // Arrange
        User::factory()->count(20)->create();

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/admin/users');

        // Assert
        $response->assertStatus(200);
        $this->assertCount(15, $response->json('users')); // Default per_page is 15
        $this->assertEquals(1, $response->json('pagination.current_page'));
        $this->assertEquals(15, $response->json('pagination.per_page'));
    }

    public function test_admin_can_get_specific_user()
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson("/api/admin/users/{$user->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'is_email_verified'
                ]
            ])
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'name' => 'John Doe',
                    'email' => 'john@example.com'
                ]
            ]);
    }

    public function test_admin_gets_404_for_nonexistent_user()
    {
        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/admin/users/999');

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'message' => 'User with ID 999 not found'
            ]);
    }

    public function test_pagination_parameters_are_validated()
    {
        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/admin/users?page=0&per_page=200');

        // Assert
        $response->assertStatus(200);
        // Deve usar valores padrão quando inválidos
        $this->assertEquals(1, $response->json('pagination.current_page'));
        $this->assertEquals(15, $response->json('pagination.per_page'));
    }

    public function test_regular_user_cannot_access_user_management()
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123')
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'password123'
        ]);

        $userToken = $loginResponse->json('token');

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $userToken
        ])->getJson('/api/admin/users');

        // Assert
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. Admin privileges required.'
            ]);
    }

    public function test_unauthenticated_user_cannot_access_user_management()
    {
        // Act
        $response = $this->getJson('/api/admin/users');

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    public function test_users_are_ordered_by_created_at_desc()
    {
        // Arrange
        $user1 = User::factory()->create(['created_at' => now()->subDays(2)]);
        $user2 = User::factory()->create(['created_at' => now()->subDays(1)]);
        $user3 = User::factory()->create(['created_at' => now()]);

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/admin/users?per_page=10');

        // Assert
        $response->assertStatus(200);
        $users = $response->json('users');
        
        // Verificar se está ordenado por created_at desc (mais recente primeiro)
        $this->assertEquals($user3->id, $users[0]['id']);
        $this->assertEquals($user2->id, $users[1]['id']);
        $this->assertEquals($user1->id, $users[2]['id']);
    }

    public function test_an_admin_without_user_read_cannot_list_users(): void
    {
        // The gap this file used to encode. An admin holding no user
        // permissions could read every end user in the workspace.
        $other = Admin::factory()->create([
            'email' => 'nopermissions@dashboard.com',
            'password' => bcrypt('password123'),
            'is_super_admin' => false,
        ]);

        $token = $this->postJson('/api/admin/login', [
            'email' => 'nopermissions@dashboard.com',
            'password' => 'password123',
        ])->json('token');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }

    public function test_admin_can_create_a_user(): void
    {
        // routes/api.php had advertised this endpoint against a method that
        // did not exist — a 500, not a 404.
        $this->withHeaders($this->headers())
            ->postJson('/api/admin/users', [
                'name' => 'Ana Ferreira',
                'email' => 'ana@tenant.test',
                'password' => 'password123',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Ana Ferreira');

        $this->assertDatabaseHas('users', ['email' => 'ana@tenant.test']);

        // An administrator vouching for the address is the verification.
        $this->assertNotNull(User::where('email', 'ana@tenant.test')->value('email_verified_at'));
    }

    public function test_admin_can_update_a_users_name(): void
    {
        $user = User::factory()->create(['name' => 'Before']);

        $this->withHeaders($this->headers())
            ->putJson("/api/admin/users/{$user->id}", ['name' => 'After'])
            ->assertOk()
            ->assertJsonPath('data.name', 'After');
    }

    public function test_the_email_is_not_editable_through_this_endpoint(): void
    {
        // Not an omission: the e-mail is the login identity and the target of
        // the verification flow, so changing it is a re-verification rather
        // than a field edit.
        $user = User::factory()->create(['email' => 'original@tenant.test']);

        $this->withHeaders($this->headers())
            ->putJson("/api/admin/users/{$user->id}", ['email' => 'hijacked@tenant.test'])
            ->assertOk();

        $this->assertSame('original@tenant.test', $user->refresh()->email);
    }

    public function test_a_user_read_carries_the_custom_field_context(): void
    {
        // So a form knows which controls to draw without a second request.
        $user = User::factory()->create();

        $this->withHeaders($this->headers())
            ->getJson("/api/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonStructure(['user', 'custom_fields', 'custom']);
    }

    public function test_admin_can_delete_a_user(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/admin/users/{$user->id}")
            ->assertOk();

        // Soft delete — the row has to survive for the audit log and for
        // anything historical pointing at this person.
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
