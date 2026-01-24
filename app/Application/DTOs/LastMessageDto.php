<?php

namespace App\Application\DTOs;

class LastMessageDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $content,
        public readonly string $senderType,
        public readonly int $senderId,
        public readonly string $createdAt
    ) {}

    /**
     * Converte o DTO em array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'sender_type' => $this->senderType,
            'sender_id' => $this->senderId,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * Cria DTO a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            content: $data['content'],
            senderType: $data['sender_type'],
            senderId: $data['sender_id'],
            createdAt: $data['created_at']
        );
    }
} 