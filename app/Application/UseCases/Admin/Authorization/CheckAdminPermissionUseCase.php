<?php

namespace App\Application\UseCases\Admin\Authorization;

use App\Domain\Interfaces\AuthorizableUser;
use App\Models\Admin;

class CheckAdminPermissionUseCase
{
    public function execute(AuthorizableUser $user, string $permissionSlug): bool
    {
        // Super admin bypass
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get admin model with roles and permissions
        $adminModel = Admin::with(['roles.permissions'])->find($user->getId());
        
        if (!$adminModel) {
            return false;
        }

        // Check if admin has the required permission through roles
        foreach ($adminModel->roles as $role) {
            if (!$role->is_active) {
                continue;
            }

            foreach ($role->permissions as $permission) {
                if ($permission->slug === $permissionSlug && $permission->is_active) {
                    return true;
                }
            }
        }

        return false;
    }

    public function executeMultiple(AuthorizableUser $user, array $permissionSlugs): bool
    {
        foreach ($permissionSlugs as $permissionSlug) {
            if (!$this->execute($user, $permissionSlug)) {
                return false;
            }
        }

        return true;
    }

    public function executeAny(AuthorizableUser $user, array $permissionSlugs): bool
    {
        foreach ($permissionSlugs as $permissionSlug) {
            if ($this->execute($user, $permissionSlug)) {
                return true;
            }
        }

        return false;
    }
}
