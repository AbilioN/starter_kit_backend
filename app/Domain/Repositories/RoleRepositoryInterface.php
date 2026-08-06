<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Role;

interface RoleRepositoryInterface
{
    public function findById(string $id): ?Role;
    public function findBySlug(string $slug): ?Role;
    public function findByName(string $name): ?Role;
    public function findAll(): array;
    public function create(string $name, string $description): Role;
    public function update(string $id, string $slug, string $name, string $description): Role;
    public function delete(string $id): void;
    public function updatePermissions(string $roleId, array $permissionIds): Role;
    public function attachPermissions(string $roleId, array $permissionIds): Role; // Para compatibilidade
}