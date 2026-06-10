<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Chat;
use App\Domain\Entities\ChatUser;

interface ChatRepositoryInterface
{
    public function findOrCreatePrivateChat(ChatUser $sender, ChatUser $receiver): Chat;
    
    public function findById(string $id): ?Chat;

    public function getUserChats(ChatUser $user, int $page = 1, int $perPage = 20): \App\Domain\Entities\Chats;

    public function createGroupChat(string $name, string $description, ChatUser $createdBy): Chat;

    public function addParticipantToChat(string $chatId, ChatUser $user): void;

    public function removeParticipantFromChat(string $chatId, ChatUser $user): void;

    public function markChatAsReadForUser(string $chatId, ChatUser $user): void;

    public function getUnreadCount(ChatUser $user): int;

    public function hasParticipant(string $chatId, ChatUser $user): bool;

    public function hasAssistant(string $chatId): bool;
} 