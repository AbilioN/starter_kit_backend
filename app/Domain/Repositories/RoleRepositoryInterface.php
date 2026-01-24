<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Role;

interface RoleRepositoryInterface
{
    public function findById(int $id): ?Role;
    public function findBySlug(string $slug): ?Role;
    public function findByName(string $name): ?Role;
    public function findAll(): array;
    public function create(string $name, string $description): Role;
    public function update(int $id, string $slug, string $name, string $description): Role;
    public function delete(int $id): void;
    public function updatePermissions(int $roleId, array $permissionIds): Role;
    public function attachPermissions(int $roleId, array $permissionIds): Role; // Para compatibilidade
}