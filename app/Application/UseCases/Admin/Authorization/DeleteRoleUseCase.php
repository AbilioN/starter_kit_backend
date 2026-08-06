<?php

namespace App\Application\UseCases\Admin\Authorization;

use App\Domain\Entities\Role;
use App\Domain\Repositories\RoleRepositoryInterface;

class DeleteRoleUseCase
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository
    ) {}    

    public function execute(string $id): void
    {
        $this->roleRepository->delete($id);
    }
}