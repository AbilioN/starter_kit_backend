<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function findById(int $id): ?User;
    public function findAllPaginated(int $page, int $perPage): array;
    public function save(User $user): User;
}