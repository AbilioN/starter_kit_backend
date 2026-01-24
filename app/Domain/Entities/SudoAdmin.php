<?php

namespace App\Domain\Entities;

use App\Domain\Interfaces\SudoAdminInterface;
use DateTime;

class SudoAdmin implements SudoAdminInterface
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly bool $isActive = true,
        public readonly ?DateTime $lastLoginAt = null,
        public readonly ?DateTime $createdAt = null,
        public readonly ?DateTime $updatedAt = null
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isSuperAdmin(): bool
    {
        return true; // SudoAdmin é sempre super admin
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function hasPermission(string $permissionSlug): bool
    {
        // SudoAdmin tem todas as permissões
        return true;
    }

    public function canManageSystem(): bool
    {
        return true;
    }

    public function canBypassAllPermissions(): bool
    {
        return true;
    }

    public function getAdminLevel(): string
    {
        return 'sudo';
    }

    public function validatePassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    public function generateToken(): string
    {
        return md5($this->email . time() . 'sudo-admin');
    }
}