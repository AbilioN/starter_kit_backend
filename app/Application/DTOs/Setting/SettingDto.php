<?php

namespace App\Application\DTOs\Setting;

class SettingDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $key,
        public readonly mixed $value,
        public readonly string $type,
        public readonly string $group,
        public readonly string $label,
        public readonly ?string $description,
        public readonly bool $is_public,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type,
            'group' => $this->group,
            'label' => $this->label,
            'description' => $this->description,
            'is_public' => $this->is_public,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
