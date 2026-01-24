<?php

namespace App\Domain\Services;

use App\Domain\Entities\Role;

interface RoleManagementServiceInterface
{
    public function createRole(string $name, string $description, string $display_name): Role;

    public function updateRole(int $roleId, string $name, string $description, string $display_name): void;

    public function deleteRole(int $roleId): void;

    public function assignRole(int $adminId, int $roleId): void;

    public function removeRole(int $adminId, int $roleId): void;

    public function getRoles(int $adminId): array;

    public function getPermissions(int $adminId): array;
    
}
