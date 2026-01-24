<?php

namespace Tests\Integration\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\EmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class EmailVerificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_verify_email_with_valid_code(): void
    {
        // Criar usuário
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => null
        ]);

        // Criar código de verificação
        $verification = EmailVerification::create([
            'user_id' => $user->id,
            'email' => 'test@example.com',
            'code' => '123456',
            'expires_at' => Carbon::now()->addMinutes(30)
        ]);

        $response = $this->postJson('/api/verify-email', [
            'email' => 'test@example.com',
            'code' => '123456'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email verified successfully',
                'email' => 'test@example.com'
            ]);

        // Verificar se o usuário foi marcado como verificado
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
        
        $user = \App\Models\User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user->email_verified_at);

        // Verificar se o código foi marcado como verificado
        $this->assertDatabaseHas('email_verifications', [
            'email' => 'test@example.com',
            'code' => '123456',
            'verified_at' => now()
        ]);
    }

    public function test_verify_email_with_invalid_code(): void
    {
        // Criar usuário
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => null
        ]);

        // Criar código de verificação
        EmailVerification::create([
            'user_id' => $user->id,
            'email' => 'test@example.com',
            'code' => '123456',
            'expires_at' => Carbon::now()->addMinutes(30)
        ]);

        $response = $this->postJson('/api/verify-email', [
            'email' => 'test@example.com',
            'code' => '999999'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid or expired verification code'
            ]);
    }

    public function test_verify_email_with_expired_code(): void
    {
        // Criar usuário
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => null
        ]);

        // Criar código de verificação expirado
        EmailVerification::create([
            'user_id' => $user->id,
            'email' => 'test@example.com',
            'code' => '123456',
            'expires_at' => Carbon::now()->subMinutes(1)
        ]);

        $response = $this->postJson('/api/verify-email', [
            'email' => 'test@example.com',
            'code' => '123456'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid or expired verification code'
            ]);
    }

    public function test_verify_email_with_non_existent_email(): void
    {
        $response = $this->postJson('/api/verify-email', [
            'email' => 'nonexistent@example.com',
            'code' => '123456'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid or expired verification code'
            ]);
    }

    public function test_verify_email_with_invalid_data(): void
    {
        $response = $this->postJson('/api/verify-email', [
            'email' => 'invalid-email',
            'code' => '123'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'code']);
    }
} 