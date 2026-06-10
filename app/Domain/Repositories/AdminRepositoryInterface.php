<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Admin;

interface AdminRepositoryInterface
{
    public function findById(string $id): ?Admin;
    public function findByEmail(string $email): ?Admin;
    public function findByIdWithRolesAndPermissions(string $id): ?Admin;
    public function findAll(): array;
    public function findAllPaginated(
        int $page = 1,
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null
    ): array;
    public function create(string $name, string $email, string $password, bool $isActive = true): Admin;
    public function update(
        string $id,
        ?string $name = null,
        ?string $email = null,
        ?string $password = null,
        ?bool $isActive = null
    ): Admin;
    public function delete(string $id): void;
    public function updateLastLogin(string $id): void;
} 