<?php

namespace App\Application\UseCases\Admin\Authorization;

use App\Domain\Entities\Role;
use App\Domain\Repositories\RoleRepositoryInterface;
use Illuminate\Support\Str;
class UpdateRoleUseCase
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository
    ) {}

    public function execute(int $id, string $name, string $description): Role
    {
        $slug = Str::slug($name);
        return $this->roleRepository->update($id, $slug, $name, $description);
    }
}