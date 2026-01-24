<?php

namespace App\Application\UseCases\Admin\Authorization;

use App\Domain\Entities\Admin;
use App\Domain\Entities\Role;
use App\Domain\Services\AuthorizationServiceInterface;
use App\Domain\Services\RoleManagementServiceInterface;

class AssignRoleUseCase
{
    public function __construct(
        private RoleManagementServiceInterface $roleManagementService,
        private AuthorizationServiceInterface $authorizationService
    ) {}

    public function execute(Admin $admin, Role $role , Admin $assignedBy): void
    {
        
        $this->roleManagementService->assignRole($admin->id, $role->id);
        $this->authorizationService->assignRole($admin->id, $role->id);
    }

}