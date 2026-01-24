<?php

namespace App\Domain\Services;

use App\Domain\Entities\Role;

interface AuthorizationServiceInterface
{
    public function canAccess(string $resource, string $action): bool;

    public function hasRole(string $role): bool;

    public function hasPermission(string $permission): bool;

    public function getRoles(Admin $admin): array;

    public function getPermissions(Admin $admin): array;

}