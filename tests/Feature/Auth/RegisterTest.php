<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data()
    {
        // Arrange
        $registerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        // Act
        $response = $this->postJson('/api/register', $registerData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email'
                ],
            ])
            ->assertJson([
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com'
                ]
            ]);

        // Verify user was created in database
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
    }

    public function test_user_cannot_register_with_existing_email()
    {
        // Arrange
        User::factory()->create([
            'email' => 'existing@example.com'
        ]);

        $registerData = [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        // Act
        $response = $this->postJson('/api/register', $registerData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJson([
                'message' => 'This email is already in use.',
                'errors' => [
                    'email' => [
                        'This email is already in use.'
                    ]
                ]
            ]);
    }

    public function test_user_cannot_register_without_name()
    {
        // Arrange
        $registerData = [
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        // Act
        $response = $this->postJson('/api/register', $registerData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_cannot_register_without_email()
    {
        // Arrange
        $registerData = [
            'name' => 'John Doe',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        // Act
        $response = $this->postJson('/api/register', $registerData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_register_without_password()
    {
        // Arrange
        $registerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password_confirmation' => 'password123'
        ];

        // Act
        $response = $this->postJson('/api/register', $registerData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_cannot_register_with_password_confirmation_mismatch()
    {
        // Arrange
        $registerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'differentpassword'
        ];

        // Act
        $response = $this->postJson('/api/register', $registerData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_cannot_register_with_short_password()
    {
        // Arrange
        $registerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => '123',
            'password_confirmation' => '123'
        ];

        // Act
        $response = $this->postJson('/api/register', $registerData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_cannot_register_with_invalid_email_format()
    {
        // Arrange
        $registerData = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        // Act
        $response = $this->postJson('/api/register', $registerData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
} 