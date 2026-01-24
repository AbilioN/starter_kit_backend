<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\ChatUser;
use App\Domain\Entities\Message;

interface MessageRepositoryInterface
{
    public function create(int $chatId, string $content, ChatUser $sender, string $messageType = 'text', ?array $metadata = null): Message;
    public function findById(int $id): ?Message;
    public function getChatMessages(int $chatId, int $page = 1, int $perPage = 50): array;
    public function markAsRead(int $messageId): void;
    public function getUnreadCount(int $chatId, ChatUser $user): int;
} 