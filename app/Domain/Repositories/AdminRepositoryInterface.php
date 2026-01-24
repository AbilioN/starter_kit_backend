<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Admin;

interface AdminRepositoryInterface
{
    public function findById(int $id): ?Admin;
    public function findByEmail(string $email): ?Admin;
    public function findByIdWithRolesAndPermissions(int $id): ?Admin;
    public function findAll(): array;
    public function findAllPaginated(
        int $page = 1, 
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null
    ): array;
    public function create(string $name, string $email, string $password, bool $isActive = true): Admin;
    public function update(
        int $id,
        ?string $name = null,
        ?string $email = null,
        ?string $password = null,
        ?bool $isActive = null
    ): Admin;
    public function delete(int $id): void;
    public function updateLastLogin(int $id): void;
} 