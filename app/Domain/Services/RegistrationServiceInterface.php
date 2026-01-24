<?php

namespace App\Domain\Services;

use App\Domain\Entities\User;

interface RegistrationServiceInterface
{
    public function register(string $name, string $email, string $password): User;
} 