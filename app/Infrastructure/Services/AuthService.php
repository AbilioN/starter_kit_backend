<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\User;
use App\Domain\Services\AuthServiceInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Exceptions\AuthenticationException;
use App\Models\User as UserModel;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function authenticate(string $email, string $password): ?User
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$user->validatePassword($password)) {
            throw new AuthenticationException();
        }

        if (!$user->isEmailVerified()) {
            throw new AuthenticationException('Email not verified');
        }

        return $user;
    }

    public function generateToken(User $user): string
    {
        // Buscar o modelo Eloquent para usar Sanctum
        $userModel = UserModel::find($user->id);
        
        if (!$userModel) {
            throw new AuthenticationException('User model not found');
        }

        // Gerar token usando Sanctum
        return $userModel->createToken('api')->plainTextToken;
    }
}
