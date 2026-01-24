<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\User;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Models\User as UserModel;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        $userModel = UserModel::where('email', $email)->first();
        
        if (!$userModel) {
            return null;
        }

        return new User(
            id: $userModel->id,
            name: $userModel->name,
            email: $userModel->email,
            password: $userModel->password,
            emailVerifiedAt: $userModel->email_verified_at
        );
    }

    public function findById(int $id): ?User
    {
        $userModel = UserModel::find($id);
        
        if (!$userModel) {
            return null;
        }

        return new User(
            id: $userModel->id,
            name: $userModel->name,
            email: $userModel->email,
            password: $userModel->password,
            emailVerifiedAt: $userModel->email_verified_at
        );
    }

    public function findAllPaginated(int $page, int $perPage): array
    {
        $paginator = UserModel::select(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $users = $paginator->items();
        $userEntities = array_map(function ($userModel) {
            return [
                'id' => $userModel->id,
                'name' => $userModel->name,
                'email' => $userModel->email,
                'email_verified_at' => $userModel->email_verified_at?->format('Y-m-d H:i:s'),
                'created_at' => $userModel->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $userModel->updated_at->format('Y-m-d H:i:s')
            ];
        }, $users);

        return [
            'data' => $userEntities,
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem()
        ];
    }

    public function save(User $user): User
    {
        $userModel = UserModel::updateOrCreate(
            ['email' => $user->email],
            [
                'name' => $user->name,
                'password' => $user->password,
                'email_verified_at' => $user->emailVerifiedAt
            ]
        );

        return new User(
            id: $userModel->id,
            name: $userModel->name,
            email: $userModel->email,
            password: $userModel->password,
            emailVerifiedAt: $userModel->email_verified_at
        );
    }
}
