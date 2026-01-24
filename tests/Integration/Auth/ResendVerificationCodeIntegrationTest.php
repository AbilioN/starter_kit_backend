<?php

namespace Tests\Integration\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\EmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ResendVerificationCodeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_resend_verification_code_for_existing_user(): void
    {
        // Criar usuário não verificado
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => null
        ]);

        // Criar código de verificação antigo
        EmailVerification::create([
            'user_id' => $user->id,
            'email' => 'test@example.com',
            'code' => '123456',
            'expires_at' => Carbon::now()->addMinutes(30)
        ]);

        $response = $this->postJson('/api/resend-verification-code', [
            'email' => 'test@example.com'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Verification code sent successfully',
                'email' => 'test@example.com'
            ]);

        // Verificar se um novo código foi criado
        $this->assertDatabaseCount('email_verifications', 2);

        // Verificar se o email foi enviado
        Mail::assertSent(\App\Mail\EmailVerificationMail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_resend_verification_code_for_verified_user(): void
    {
        // Criar usuário já verificado
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now()
        ]);

        $response = $this->postJson('/api/resend-verification-code', [
            'email' => 'test@example.com'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Email already verified'
            ]);
    }

    public function test_resend_verification_code_for_non_existent_user(): void
    {
        $response = $this->postJson('/api/resend-verification-code', [
            'email' => 'nonexistent@example.com'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'User not found'
            ]);
    }

    public function test_resend_verification_code_with_invalid_email(): void
    {
        $response = $this->postJson('/api/resend-verification-code', [
            'email' => 'invalid-email'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_resend_verification_code_without_email(): void
    {
        $response = $this->postJson('/api/resend-verification-code', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
} 