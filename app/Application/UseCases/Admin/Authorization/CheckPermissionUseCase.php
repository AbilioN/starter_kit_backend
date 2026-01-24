<?php

namespace App\Application\UseCases\Admin\Authorization;

use App\Domain\Entities\Admin;
use App\Domain\Entities\Permission;
use App\Domain\Services\AuthorizationServiceInterface;

class CheckPermissionUseCase
{
    public function __construct(
        private AuthorizationServiceInterface $authorizationService
    ) {}

    public function execute(Admin $admin, Permission $permission): bool
    {
        if (!$this->authorizationService->hasPermission($permission)) {
            throw new PermissionException("Admin {$admin->id} does not have permission {$permission->id}");
        }
        return $this->authorizationService->canAccess($resource, $action);
    }
}