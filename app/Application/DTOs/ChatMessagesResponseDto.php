<?php

namespace App\Application\DTOs;

class ChatMessagesResponseDto
{
    public function __construct(
        public readonly array $messages,
        public readonly bool $fromCache,
        public readonly PaginationDto $pagination
    ) {}

    /**
     * Converte o DTO em array
     */
    public function toArray(): array
    {
        return [
            'success' => true,
            'data' => [
                'messages' => $this->messages,
                'from_cache' => $this->fromCache,
                'pagination' => $this->pagination->toArray()
            ]
        ];
    }

    /**
     * Cria DTO a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messages: $data['messages'],
            fromCache: $data['from_cache'] ?? false,
            pagination: PaginationDto::fromArray($data['pagination'])
        );
    }
}
