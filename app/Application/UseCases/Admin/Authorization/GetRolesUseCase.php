<?php

namespace App\Application\UseCases\Admin\Authorization;

use App\Domain\Repositories\RoleRepositoryInterface;

class GetRolesUseCase
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository
    ) {}

    public function execute(): array
    {
        return $this->roleRepository->findAll();
    }
}