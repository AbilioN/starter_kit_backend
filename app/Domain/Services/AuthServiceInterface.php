<?php

namespace App\Domain\Services;

use App\Domain\Entities\User;

interface AuthServiceInterface
{
    public function authenticate(string $email, string $password): ?User;
    public function generateToken(User $user): string;
}
