<?php

namespace App\Domain\Entities;

use App\Application\DTOs\Setting\SettingDto;
use DateTime;

class Setting
{
    public function __construct(
        public readonly string $id,
        public readonly string $key,
        public readonly mixed $value,
        public readonly string $rawValue,
        public readonly string $type,
        public readonly string $group,
        public readonly string $label,
        public readonly ?string $description,
        public readonly bool $isPublic,
        public readonly ?DateTime $createdAt,
        public readonly ?DateTime $updatedAt,
    ) {}

    public function toDto(): SettingDto
    {
        return new SettingDto(
            id: $this->id,
            key: $this->key,
            value: $this->value,
            type: $this->type,
            group: $this->group,
            label: $this->label,
            description: $this->description,
            is_public: $this->isPublic,
            created_at: $this->createdAt?->format('Y-m-d H:i:s'),
            updated_at: $this->updatedAt?->format('Y-m-d H:i:s'),
        );
    }
}
