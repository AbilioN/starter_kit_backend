<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Criar admin e fazer login
        $admin = Admin::factory()->create([
            'email' => 'admin3@dashboard.com',
            'password' => bcrypt('password123')
        ]);

        $loginResponse = $this->postJson('/api/admin/login', [
            'email' => 'admin3@dashboard.com',
            'password' => 'password123'
        ]);

        $this->adminToken = $loginResponse->json('token');
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
} 