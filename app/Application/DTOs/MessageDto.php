<?php

namespace App\Application\DTOs;

class MessageDto
{
    public function __construct(
        public readonly int $id,
        public readonly int $chat_id,
        public readonly string $content,
        public readonly int $sender_id,
        public readonly string $sender_type,
        public readonly string $message_type,
        public readonly ?array $metadata,
        public readonly bool $is_read,
        public readonly ?string $read_at = null,
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
            'chat_id' => $this->chat_id,
            'content' => $this->content,
            'sender_id' => $this->sender_id,
            'sender_type' => $this->sender_type,
            'message_type' => $this->message_type,
            'metadata' => $this->metadata,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at,
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
            chat_id: $data['chat_id'],
            content: $data['content'],
            sender_id: $data['sender_id'],
            sender_type: $data['sender_type'],
            message_type: $data['message_type'],
            metadata: $data['metadata'] ?? null,
            is_read: $data['is_read'],
            read_at: $data['read_at'] ?? null,
            created_at: $data['created_at'] ?? null,
            updated_at: $data['updated_at'] ?? null
        );
    }
} 