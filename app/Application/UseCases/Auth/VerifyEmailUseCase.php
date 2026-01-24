<?php

namespace App\Application\UseCases\Auth;

use App\Domain\Services\EmailVerificationServiceInterface;

class VerifyEmailUseCase
{
    public function __construct(
        private EmailVerificationServiceInterface $emailVerificationService
    ) {}

    public function execute(string $email, string $code): array
    {
        $isVerified = $this->emailVerificationService->verifyCode($email, $code);

        if (!$isVerified) {
            throw new \App\Domain\Exceptions\RegistrationException('Invalid or expired verification code');
        }

        return [
            'message' => 'Email verified successfully',
            'email' => $email
        ];
    }
} 