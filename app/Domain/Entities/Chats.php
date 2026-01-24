<?php

namespace App\Domain\Entities;

use App\Application\DTOs\ChatListResponseDto;
use App\Application\DTOs\ChatListItemDto;
use App\Application\DTOs\PaginationDto;

class Chats
{
    /**
     * @param Chat[] $chats
     * @param int $currentPage
     * @param int $perPage
     * @param int $total
     * @param int $lastPage
     * @param int|null $from
     * @param int|null $to
     */
    public function __construct(
        public readonly array $chats,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
        public readonly ?int $from = null,
        public readonly ?int $to = null
    ) {}

    /**
     * Converts the domain entity to DTO for API responses
     * Note: This method creates basic DTOs. For complete information including
     * last messages, unread counts, and participant counts, the repository
     * should provide enriched data or this method should be called with
     * additional context from the repository layer.
     */
    public function toDto(): ChatListResponseDto
    {
        $chatDtos = array_map(function (Chat $chat) {
            return new ChatListItemDto(
                id: $chat->id,
                name: $chat->name,
                type: $chat->type,
                description: $chat->description,
                lastMessage: null, // Should be populated by repository or service layer
                unreadCount: 0, // Should be calculated by repository or service layer
                participantsCount: 0, // Should be calculated by repository or service layer
                createdAt: $chat->createdAt?->format('Y-m-d H:i:s') ?? '',
                updatedAt: $chat->updatedAt?->format('Y-m-d H:i:s') ?? ''
            );
        }, $this->chats);

        $paginationDto = new PaginationDto(
            currentPage: $this->currentPage,
            perPage: $this->perPage,
            total: $this->total,
            lastPage: $this->lastPage,
            from: $this->from,
            to: $this->to
        );

        return new ChatListResponseDto(
            chats: $chatDtos,
            pagination: $paginationDto
        );
    }

    /**
     * Converts the domain entity to array
     */
    public function toArray(): array
    {
        return [
            'chats' => array_map(fn(Chat $chat) => $chat->toArray(), $this->chats),
            'pagination' => [
                'current_page' => $this->currentPage,
                'per_page' => $this->perPage,
                'total' => $this->total,
                'last_page' => $this->lastPage,
                'from' => $this->from,
                'to' => $this->to
            ]
        ];
    }

    /**
     * Gets the total number of chats
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Gets the current page
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Gets the number of items per page
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Gets the last page number
     */
    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Checks if there are more pages
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Checks if there are previous pages
     */
    public function hasPreviousPages(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Gets the number of chats in the current page
     */
    public function getCount(): int
    {
        return count($this->chats);
    }

    /**
     * Checks if the list is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->chats);
    }
}
