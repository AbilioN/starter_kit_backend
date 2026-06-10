<?php

namespace App\Domain\Interfaces;

interface AuthorizableUser
{
    public function getId(): string;
    public function getName(): string;
    public function getEmail(): string;
    public function isSuperAdmin(): bool;
    public function isActive(): bool;
    public function hasPermission(string $permissionSlug): bool;
}
