<?php

namespace App\Application\Services;

use App\Domain\Entities\Admin;
use App\Domain\Entities\SudoAdmin;
use App\Domain\Interfaces\AuthorizableUser;
use App\Models\Admin as AdminModel;

class AdminFactory
{
    public static function createFromModel(AdminModel $adminModel): AuthorizableUser
    {
        if ($adminModel->is_super_admin) {
            return new SudoAdmin(
                id: $adminModel->id,
                name: $adminModel->name,
                email: $adminModel->email,
                password: $adminModel->password,
                isActive: $adminModel->is_active,
                lastLoginAt: $adminModel->last_login_at,
                createdAt: $adminModel->created_at,
                updatedAt: $adminModel->updated_at
            );
        }

        return new Admin(
            id: $adminModel->id,
            name: $adminModel->name,
            email: $adminModel->email,
            password: $adminModel->password,
            isActive: $adminModel->is_active,
            isSuperAdmin: $adminModel->is_super_admin,
            lastLoginAt: $adminModel->last_login_at,
            createdAt: $adminModel->created_at,
            updatedAt: $adminModel->updated_at
        );
    }
}
