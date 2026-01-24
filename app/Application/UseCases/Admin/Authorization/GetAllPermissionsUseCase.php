<?php

namespace App\Application\UseCases\Admin\Authorization;

use App\Domain\Repositories\PermissionRepositoryInterface;

class GetAllPermissionsUseCase
{
    public function __construct(
        private PermissionRepositoryInterface $permissionRepository
    ) {}

    public function execute(): array
    {
        $permissions = $this->permissionRepository->findAll();
        
        return array_map(function ($permission) {
            return $permission->toDto()->toArray();
        }, $permissions);
    }
}
