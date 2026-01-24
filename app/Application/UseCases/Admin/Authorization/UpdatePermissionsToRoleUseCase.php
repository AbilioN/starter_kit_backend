<?php

namespace App\Application\UseCases\Admin\Authorization;

use App\Domain\Entities\Role;
use App\Domain\Repositories\PermissionRepositoryInterface;
use App\Domain\Repositories\RoleRepositoryInterface;

class UpdatePermissionsToRoleUseCase
{
    public function __construct(
        private PermissionRepositoryInterface $permissionRepository,
        private RoleRepositoryInterface $roleRepository
    ) {}

    public function execute(int $roleId, array $permissionIds): Role
    {
        if (empty($permissionIds)) {
            $this->roleRepository->updatePermissions($roleId, []);
            return $this->roleRepository->findById($roleId);
        }
        $existingPermissions = $this->permissionRepository->findByIds($permissionIds);
        $existingPermissionIds = array_map(fn($permission) => $permission->getId(), $existingPermissions);
        $missingIds = array_diff($permissionIds, $existingPermissionIds);
        if (!empty($missingIds)) {
            throw new \InvalidArgumentException(
                'Some permissions do not exist. Missing permission IDs: ' . implode(', ', $missingIds)
            );
        }
        $this->roleRepository->updatePermissions($roleId, $permissionIds);
        return $this->roleRepository->findById($roleId);
    }
}
