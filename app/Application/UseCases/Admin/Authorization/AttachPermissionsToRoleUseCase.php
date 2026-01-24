<?php

namespace App\Application\UseCases\Admin\Authorization;

use App\Domain\Entities\Role;
use App\Domain\Repositories\PermissionRepositoryInterface;
use App\Domain\Repositories\RoleRepositoryInterface;

class AttachPermissionsToRoleUseCase
{
    public function __construct(
        private PermissionRepositoryInterface $permissionRepository,
        private RoleRepositoryInterface $roleRepository
    ) {}

    public function execute(int $roleId, array $permissionIds): Role
    {
        // Valida se todas as permissions existem
        $existingPermissions = $this->permissionRepository->findByIds($permissionIds);
        if (count($existingPermissions) !== count($permissionIds)) {
            throw new \InvalidArgumentException('Some permissions do not exist');
        }
        return $this->roleRepository->attachPermissions($roleId, $permissionIds);
    }
}