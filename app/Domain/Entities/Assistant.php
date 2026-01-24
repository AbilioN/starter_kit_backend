<?php

namespace App\Domain\Entities;

class Assistant implements ChatUser
{
    public function __construct(
        private int $id,
        private string $name,
        private ?string $description,
        private ?string $avatar,
        private array $capabilities,
        private bool $isActive
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getEmail(): ?string
    {
        return null; // Assistentes não têm email
    }

    public function getType(): string
    {
        return 'assistant';
    }

    public function toDto(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'avatar' => $this->avatar,
            'capabilities' => $this->capabilities,
            'is_active' => $this->isActive,
            'type' => $this->getType()
        ];
    }
}
