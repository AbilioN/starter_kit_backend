<?php

namespace App\Application\UseCases\Auth;

use App\Domain\Services\RegistrationServiceInterface;

class RegisterUseCase
{
    public function __construct(
        private RegistrationServiceInterface $registrationService
    ) {}

    public function execute(string $name, string $email, string $password): array
    {
        $user = $this->registrationService->register($name, $email, $password);

        return [
            'message' => 'User registered successfully. Please check your email for verification code.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ];
    }
} 