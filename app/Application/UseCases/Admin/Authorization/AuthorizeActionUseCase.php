<?php

namespace App\Application\UseCases\Admin\Authorization;

use App\Domain\Interfaces\AuthorizableUser;
use App\Domain\Exceptions\AuthorizationException;

class AuthorizeActionUseCase
{
    public function __construct(
        private CheckAdminPermissionUseCase $checkPermissionUseCase
    ) {}

    public function execute(AuthorizableUser $user, string $permissionSlug): void
    {
        // SudoAdmin tem bypass automático
        if ($user instanceof \App\Domain\Entities\SudoAdmin) {
            return;
        }

        if (!$this->checkPermissionUseCase->execute($user, $permissionSlug)) {
            throw new AuthorizationException(
                "Admin {$user->getId()} does not have permission to perform this action. Required permission: {$permissionSlug}"
            );
        }
    }

    public function executeMultiple(AuthorizableUser $user, array $permissionSlugs): void
    {
        foreach ($permissionSlugs as $permissionSlug) {
            $this->execute($user, $permissionSlug);
        }
    }

    public function executeAny(AuthorizableUser $user, array $permissionSlugs): void
    {
        if (!$this->checkPermissionUseCase->executeAny($user, $permissionSlugs)) {
            $permissionsList = implode(', ', $permissionSlugs);
            throw new AuthorizationException(
                "Admin {$user->getId()} does not have any of the required permissions: {$permissionsList}"
            );
        }
    }
}
