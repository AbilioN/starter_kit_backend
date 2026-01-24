<?php

namespace App\Domain\Services;

use App\Domain\Entities\Admin;

interface AdminAuthServiceInterface
{
    public function authenticate(string $email, string $password): ?Admin;
    public function generateToken(Admin $admin): string;
    public function getAdminPermissions(Admin $admin): array;
    public function getAdminRolesWithPermissions(Admin $admin): array;
} 