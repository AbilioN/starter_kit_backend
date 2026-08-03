<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\GodAdmin;
use App\Domain\Repositories\GodAdminRepositoryInterface;
use App\Models\GodAdmin as GodAdminModel;

class GodAdminRepository implements GodAdminRepositoryInterface
{
    public function findById(string $id): ?GodAdmin
    {
        $godAdmin = GodAdminModel::find($id);

        return $godAdmin?->toEntity();
    }

    public function findByEmail(string $email): ?GodAdmin
    {
        $godAdmin = GodAdminModel::where('email', $email)->first();

        return $godAdmin?->toEntity();
    }

    public function create(string $name, string $email, string $password): GodAdmin
    {
        $godAdmin = GodAdminModel::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
        ]);

        return $godAdmin->toEntity();
    }
}
