<?php

namespace App\Application\DTOs;

class ChatListResponseDto
{
    /**
     * @param ChatListItemDto[] $chats
     * @param PaginationDto $pagination
     */
    public function __construct(
        public readonly array $chats,
        public readonly PaginationDto $pagination
    ) {}

    /**
     * Converte o DTO em array
     */
    public function toArray(): array
    {
        return [
            'chats' => array_map(fn(ChatListItemDto $chat) => $chat->toArray(), $this->chats),
            'pagination' => $this->pagination->toArray()
        ];
    }

    /**
     * Cria DTO a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            chats: array_map(fn(array $chat) => ChatListItemDto::fromArray($chat), $data['chats']),
            pagination: PaginationDto::fromArray($data['pagination'])
        );
    }
} 