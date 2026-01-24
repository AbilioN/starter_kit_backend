<?php

namespace App\Domain\Entities;

use App\Application\DTOs\MessageDto;
use DateTime;

class Message
{
    public function __construct(
        public readonly int $id,
        public readonly int $chatId,
        public readonly string $content,
        public readonly ChatUser $sender,
        public readonly string $messageType,
        public readonly ?array $metadata,
        public readonly bool $isRead,
        public readonly ?DateTime $readAt = null,
        public readonly ?DateTime $createdAt = null,
        public readonly ?DateTime $updatedAt = null
    ) {}

    /**
     * Converte a entidade em DTO
     */
    public function toDto(): MessageDto
    {
        return new MessageDto(
            id: $this->id,
            chat_id: $this->chatId,
            content: $this->content,
            sender_id: $this->sender->getId(),
            sender_type: $this->sender->getType(),
            message_type: $this->messageType,
            metadata: $this->metadata,
            is_read: $this->isRead,
            read_at: $this->readAt?->format('Y-m-d H:i:s'),
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
            'chat_id' => $this->chatId,
            'content' => $this->content,
            'sender_id' => $this->sender->getId(),
            'sender_type' => $this->sender->getType(),
            'message_type' => $this->messageType,
            'metadata' => $this->metadata,
            'is_read' => $this->isRead,
            'read_at' => $this->readAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }

    public function isFromUser(): bool
    {
        return $this->sender->getType() === 'user';
    }

    public function isFromAdmin(): bool
    {
        return $this->sender->getType() === 'admin';
    }

    public function isUnread(): bool
    {
        return !$this->isRead;
    }

    public function isFromChatUser(ChatUser $chatUser): bool
    {
        return $this->sender->getId() === $chatUser->getId();
    }

    public function markAsRead(): self
    {
        return new self(
            id: $this->id,
            chatId: $this->chatId,
            content: $this->content,
            sender: $this->sender,
            messageType: $this->messageType,
            metadata: $this->metadata,
            isRead: true,
            readAt: new DateTime(),
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    public function getSenderType(): string
    {
        return $this->sender->getType();
    }

    public function getSenderId(): int
    {
        return $this->sender->getId();
    }
} 