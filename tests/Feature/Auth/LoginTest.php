<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials()
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123')
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123'
        ];

        // Act
        $response = $this->postJson('/api/login', $loginData);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email'
                ],
                'token'
            ])
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_user_cannot_login_with_invalid_email()
    {
        // Arrange
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123'
        ];

        // Act
        $response = $this->postJson('/api/login', $loginData);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials'
            ]);
    }

    public function test_user_cannot_login_with_invalid_password()
    {
        // Arrange
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123')
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'wrongpassword'
        ];

        // Act
        $response = $this->postJson('/api/login', $loginData);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials'
            ]);
    }

    public function test_login_requires_email()
    {
        // Arrange
        $loginData = [
            'password' => 'password123'
        ];

        // Act
        $response = $this->postJson('/api/login', $loginData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_password()
    {
        // Arrange
        $loginData = [
            'email' => 'john@example.com'
        ];

        // Act
        $response = $this->postJson('/api/login', $loginData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_requires_valid_email_format()
    {
        // Arrange
        $loginData = [
            'email' => 'invalid-email',
            'password' => 'password123'
        ];

        // Act
        $response = $this->postJson('/api/login', $loginData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
} 