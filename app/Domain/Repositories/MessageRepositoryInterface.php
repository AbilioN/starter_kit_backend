<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\ChatUser;
use App\Domain\Entities\Message;

interface MessageRepositoryInterface
{
    public function create(string $chatId, string $content, ChatUser $sender, string $messageType = 'text', ?array $metadata = null, ?string $replyToId = null): Message;
    public function findById(string $id): ?Message;
    public function getChatMessages(string $chatId, int $page = 1, int $perPage = 50): array;
    public function markAsRead(string $messageId): void;
    public function getUnreadCount(string $chatId, ChatUser $user): int;
} 