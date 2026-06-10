<?php

namespace App\Application\UseCases\User;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetUserProfileUseCase
{
    public function execute(string $userId): array
    {
        $user = User::findOrFail($userId);

        return [
            'success' => true,
            'data' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
                'created_at'        => $user->created_at?->format('Y-m-d H:i:s'),
            ],
        ];
    }
}
