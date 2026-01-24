<?php

namespace App\Domain\Entities;

use DateTime;

class User implements ChatUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?DateTime $emailVerifiedAt = null
    ) {}

    public function validatePassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    public function generateToken(): string
    {
        // Lógica simples de geração de token para o Domain
        return md5($this->email . time());
    }

    public function isEmailVerified(): bool
    {
        return !is_null($this->emailVerifiedAt);
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getType(): string
    {
        return 'user';
    }

    public function isActive(): bool
    {
        return $this->isEmailVerified();
    }
}