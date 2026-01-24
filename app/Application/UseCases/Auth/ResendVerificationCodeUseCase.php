<?php

namespace App\Application\UseCases\Auth;

use App\Domain\Services\EmailVerificationServiceInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Exceptions\RegistrationException;

class ResendVerificationCodeUseCase
{
    public function __construct(
        private EmailVerificationServiceInterface $emailVerificationService,
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(string $email): array
    {
        // Verificar se o usuário existe e não está verificado
        $user = $this->userRepository->findByEmail($email);
        
        if (!$user) {
            throw new RegistrationException('User not found');
        }

        if ($user->isEmailVerified()) {
            throw new RegistrationException('Email already verified');
        }

        $this->emailVerificationService->resendVerificationCode($email);

        return [
            'message' => 'Verification code sent successfully',
            'email' => $email
        ];
    }
} 