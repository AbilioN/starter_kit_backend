<?php

namespace App\Application\UseCases\Admin;

use App\Models\Admin;
use App\Models\Role;

class AssignRoleToAdminUseCase
{
    public function execute(int $adminId, int $roleId, int $assignedBy): void
    {
        $admin = Admin::findOrFail($adminId);
        $role = Role::findOrFail($roleId);
        
        // Verificar se a role está ativa
        if (!$role->is_active) {
            throw new \Exception("Cannot assign inactive role");
        }
        
        // Atribuir a role ao admin na tabela pivot com assigned_at e assigned_by
        $admin->roles()->syncWithoutDetaching([
            $roleId => [
                'assigned_at' => now(),
                'assigned_by' => $assignedBy
            ]
        ]);
    }
}

