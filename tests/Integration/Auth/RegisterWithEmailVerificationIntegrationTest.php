<?php

namespace Tests\Integration\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\EmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

class RegisterWithEmailVerificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_register_creates_user_and_sends_verification_code(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'User registered successfully. Please check your email for verification code.',
                'user' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com'
                ]
            ]);

        // Verificar se o usuário foi criado
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_verified_at' => null
        ]);

        // Verificar se o código de verificação foi criado
        $this->assertDatabaseHas('email_verifications', [
            'email' => 'test@example.com'
        ]);

        // Verificar se o email foi enviado
        Mail::assertSent(\App\Mail\EmailVerificationMail::class, function ($mail) use ($userData) {
            return $mail->hasTo($userData['email']);
        });
    }

    public function test_register_with_existing_email_returns_error(): void
    {
        // Criar usuário existente
        User::factory()->create(['email' => 'existing@example.com']);

        $userData = [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'This email is already in use.',
                'errors' => [
                    'email' => ['This email is already in use.']
                ]
            ]);
    }

    public function test_register_with_invalid_data_returns_validation_errors(): void
    {
        $userData = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => 'different'
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }
} 