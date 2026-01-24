<?php

namespace App\Application\UseCases\Admin\Authorization;

use App\Domain\Entities\Role;
use App\Domain\Repositories\RoleRepositoryInterface;

class CreateRoleUseCase
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository
    ) {}

    public function execute(string $name, string $description): Role
    {
        return $this->roleRepository->create($name, $description);
    }
}