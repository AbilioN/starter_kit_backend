<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\AdminRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        
        // Seed the database
/*         $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class); */
    }

    public function test_admin_can_access_protected_route()
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin2@dashboard.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'is_super_admin' => false
        ]);
        // Arrange - Use admin from seeder
        $loginResponse = $this->postJson('/api/admin/login', [
            'email' => 'admin2@dashboard.com',
            'password' => 'password123'
        ]);

        
        $token = $loginResponse->json('token');
        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/dashboard');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Welcome to Admin Dashboard'
            ]);
    }

    public function test_regular_user_cannot_access_admin_route()
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

        $token = $loginResponse->json('token');

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/dashboard');

        // Assert
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. Admin privileges required.'
            ]);
    }

    public function test_inactive_admin_cannot_access_protected_route()
    {
        $admin = Admin::factory()->inactive()->create([
            'email' => 'admin3@dashboard.com',
            'password' => bcrypt('password123')
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/dashboard');
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. Admin privileges required.'
            ]);
    }

    public function test_unauthenticated_user_cannot_access_admin_route()
    {
        // Act
        $response = $this->getJson('/api/admin/dashboard');

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    public function test_invalid_token_cannot_access_admin_route()
    {
        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token'
        ])->getJson('/api/admin/dashboard');

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }
} 