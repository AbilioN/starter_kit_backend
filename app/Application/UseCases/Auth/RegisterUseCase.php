<?php

namespace App\Application\UseCases\Auth;

use App\Application\UseCases\Tenant\EnforcePlanLimitUseCase;
use App\Domain\Services\RegistrationServiceInterface;
use App\Models\User;

class RegisterUseCase
{
    public function __construct(
        private RegistrationServiceInterface $registrationService,
        private EnforcePlanLimitUseCase $enforcePlanLimit,
    ) {}

    public function execute(string $name, string $email, string $password): array
    {
        $this->enforcePlanLimit->execute('max_users', fn () => User::count());

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