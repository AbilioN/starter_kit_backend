<?php

namespace App\Application\UseCases\User;

use App\Models\User;

class UpdateUserProfileUseCase
{
    public function execute(string $userId, string $name): array
    {
        $user = User::findOrFail($userId);
        $user->update(['name' => $name]);

        return [
            'success' => true,
            'data' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
                'updated_at'        => $user->updated_at?->format('Y-m-d H:i:s'),
            ],
        ];
    }
}
