<?php

namespace App\Domain\Entities;

use App\Application\DTOs\ChatDto;
use DateTime;

class Chat
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        // Nullable, because the column is and because CreatePrivateChatUseCase
        // never sets one — a private chat is between two people and has nothing
        // to describe. Requiring a string here made every private chat created
        // through the app fatal on the way back out: toEntityFromReciever()
        // passes the column straight through, so `GET /api/chats` answered 500
        // for anyone who had one.
        public readonly ?string $description,
        public readonly string $createdBy,
        public readonly ?string $createdByType = null,
        public readonly ?DateTime $createdAt = null,
        public readonly ?DateTime $updatedAt = null
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }
    
    public function getDescription(): string
    {
        return $this->description;
    }
    
    
    
    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }
    
    
    public function getCreatedByType(): ?string
    {
        return $this->createdByType;
    }
    
    
    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }
    
    
    /**
     * Converte a entidade em DTO
     */
    public function toDto(): ChatDto
    {
        return new ChatDto(
            id: $this->id,
            name: $this->name,
            type: $this->type,
            description: $this->description,
            created_by: $this->createdBy,
            created_by_type: $this->createdByType,
            created_at: $this->createdAt?->format('Y-m-d H:i:s'),
            updated_at: $this->updatedAt?->format('Y-m-d H:i:s')
        );
    }

    /**
     * Converte a entidade em array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'created_by' => $this->createdBy,
            'created_by_type' => $this->createdByType,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Verifica se é um chat privado
     */
    public function isPrivate(): bool
    {
        return $this->type === 'private';
    }

    /**
     * Verifica se é um chat em grupo
     */
    public function isGroup(): bool
    {
        return $this->type === 'group';
    }
} 