<?php

namespace App\Application\UseCases\Admin;

use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Exceptions\UserNotFoundException;

class GetUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        
        if (!$user) {
            throw new UserNotFoundException("User with ID {$userId} not found");
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->emailVerifiedAt?->format('Y-m-d H:i:s'),
                'is_email_verified' => $user->isEmailVerified()
            ]
        ];
    }
} 