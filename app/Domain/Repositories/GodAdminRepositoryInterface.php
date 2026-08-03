<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\GodAdmin;

interface GodAdminRepositoryInterface
{
    public function findById(string $id): ?GodAdmin;
    public function findByEmail(string $email): ?GodAdmin;
    public function create(string $name, string $email, string $password): GodAdmin;
}
