<?php

namespace App\Application\UseCases\Admin;

use App\Models\Admin;

class UpdateAdminProfileUseCase
{
    public function execute(string $adminId, string $name): array
    {
        $admin = Admin::findOrFail($adminId);
        $admin->update(['name' => $name]);

        return [
            'success' => true,
            'data' => [
                'id'             => $admin->id,
                'name'           => $admin->name,
                'email'          => $admin->email,
                'is_active'      => $admin->is_active,
                'is_super_admin' => $admin->is_super_admin,
                'last_login_at'  => $admin->last_login_at?->format('Y-m-d H:i:s'),
                'updated_at'     => $admin->updated_at?->format('Y-m-d H:i:s'),
            ],
        ];
    }
}
