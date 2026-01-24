<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\User;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Exceptions\RegistrationException;
use App\Mail\EmailVerificationMail;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailVerificationService implements EmailVerificationServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function sendVerificationCode(User $user): void
    {
        // Gerar código de 6 dígitos
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Criar registro de verificação
        EmailVerification::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code' => $code,
            'expires_at' => now()->addMinutes(30),
        ]);

        // Enviar email
        Mail::to($user->email)->send(new EmailVerificationMail($code, $user->name));
    }

    public function verifyCode(string $email, string $code): bool
    {
        // Buscar o código mais recente e não expirado
        $verification = EmailVerification::where('email', $email)
            ->where('expires_at', '>', now())
            ->whereNull('verified_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$verification || $verification->code !== $code) {
            return false;
        }

        // Marcar como verificado
        $verification->markAsVerified();

        // Marcar usuário como verificado
        $user = $this->userRepository->findByEmail($email);
        if ($user) {
            $userModel = \App\Models\User::find($user->id);
            if ($userModel) {
                $userModel->markEmailAsVerified();
            }
        }

        return true;
    }

    public function resendVerificationCode(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);
        
        if (!$user) {
            throw new RegistrationException('User not found');
        }

        // Invalidar apenas os códigos que existiam antes do reenvio
        $timestampBeforeResend = now();
        
        EmailVerification::where('email', $email)
            ->whereNull('verified_at')
            ->where('created_at', '<', $timestampBeforeResend)
            ->update(['verified_at' => now()]);

        // Enviar novo código
        $this->sendVerificationCode($user);
    }
} 