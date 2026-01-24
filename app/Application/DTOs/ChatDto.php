<?php

namespace App\Application\DTOs;

class ChatDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $description,
        public readonly int $created_by,
        public readonly ?string $created_by_type = null,
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null
    ) {}

    /**
     * Converte o DTO em array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'created_by_type' => $this->created_by_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Cria DTO a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            type: $data['type'],
            description: $data['description'],
            created_by: $data['created_by'],
            created_by_type: $data['created_by_type'] ?? null,
            created_at: $data['created_at'] ?? null,
            updated_at: $data['updated_at'] ?? null
        );
    }
} 