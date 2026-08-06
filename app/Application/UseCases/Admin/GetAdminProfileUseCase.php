<?php

namespace App\Application\UseCases\Admin;

use App\Models\Admin;

class GetAdminProfileUseCase
{
    public function execute(string $adminId): array
    {
        $admin = Admin::findOrFail($adminId);

        return [
            'success' => true,
            'data' => [
                'id'             => $admin->id,
                'name'           => $admin->name,
                'email'          => $admin->email,
                'is_active'      => $admin->is_active,
                'is_super_admin' => $admin->is_super_admin,
                'is_tenant_owner' => $admin->is_tenant_owner,
                'last_login_at'  => $admin->last_login_at?->format('Y-m-d H:i:s'),
                'created_at'     => $admin->created_at?->format('Y-m-d H:i:s'),
            ],
        ];
    }
}
