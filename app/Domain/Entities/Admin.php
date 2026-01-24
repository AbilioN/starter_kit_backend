<?php

namespace App\Domain\Entities;

use App\Application\DTOs\Admin\Authorization\AdminDto;
use App\Domain\Interfaces\AuthorizableUser;
use DateTime;

class Admin implements ChatUser, AuthorizableUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly bool $isActive,
        public readonly bool $isSuperAdmin = false,
        public readonly ?DateTime $lastLoginAt = null,
        public readonly ?DateTime $createdAt = null,
        public readonly ?DateTime $updatedAt = null
    ) {}

    public function validatePassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    public function generateToken(): string
    {
        // Lógica simples de geração de token para o Domain
        return md5($this->email . time() . 'admin');
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    // Implementação da interface ChatUser

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

    public function getType(): string
    {
        return 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function hasPermission(string $permissionSlug): bool
    {
        // Admin comum não tem permissões diretas
        // As permissões são verificadas através de roles no UseCase
        return false;
    }

    public function toDto(): AdminDto
    {
        return new AdminDto(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            is_active: $this->isActive,
            is_super_admin: $this->isSuperAdmin,
            last_login_at: $this->lastLoginAt?->format('Y-m-d H:i:s'),
            created_at: $this->createdAt?->format('Y-m-d H:i:s'),
            updated_at: $this->updatedAt?->format('Y-m-d H:i:s'),
        );
    }

} 