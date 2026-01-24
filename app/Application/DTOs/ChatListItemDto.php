<?php

namespace App\Application\DTOs;

class ChatListItemDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $description,
        public readonly ?LastMessageDto $lastMessage,
        public readonly int $unreadCount,
        public readonly int $participantsCount,
        public readonly string $createdAt,
        public readonly string $updatedAt
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
            'last_message' => $this->lastMessage?->toArray(),
            'unread_count' => $this->unreadCount,
            'participants_count' => $this->participantsCount,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
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
            lastMessage: isset($data['last_message']) ? LastMessageDto::fromArray($data['last_message']) : null,
            unreadCount: $data['unread_count'],
            participantsCount: $data['participants_count'],
            createdAt: $data['created_at'],
            updatedAt: $data['updated_at']
        );
    }
} 