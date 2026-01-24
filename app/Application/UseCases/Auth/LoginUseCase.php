<?php
namespace App\Application\UseCases\Auth;

use App\Domain\Services\AuthServiceInterface;

class LoginUseCase
{
    public function __construct(
        private AuthServiceInterface $authService
    ) {}

    public function execute(string $email, string $password): array
    {
        $user = $this->authService->authenticate($email, $password);
        $token = $this->authService->generateToken($user);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->emailVerifiedAt?->format('Y-m-d H:i:s')
            ],
            'token' => $token
        ];
    }
}
