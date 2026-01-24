<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Permission;

interface PermissionRepositoryInterface
{
    public function findByIds(array $ids): array;

    public function findAll(): array;

    public function create(string $name, string $description, string $resource, string $action): Permission;

    public function update(int $id, string $name, string $description, string $resource, string $action): Permission;

    public function delete(int $id): void;

    public function attachRoles(int $id, array $roles): void;

    public function detachRoles(int $id, array $roles): void;

}