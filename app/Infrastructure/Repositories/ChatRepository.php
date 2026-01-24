<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Chat;
use App\Domain\Entities\ChatUser;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Models\Chat as ChatModel;

class ChatRepository implements ChatRepositoryInterface
{
    public function findOrCreatePrivateChat(ChatUser $sender, ChatUser $reciever): Chat
    {

        $chatModel = ChatModel::findOrCreatePrivateChat($sender, $reciever);
        return $chatModel->toEntityFromReciever($reciever);
    }

    public function findById(int $id): ?Chat
    {
        $chatModel = ChatModel::find($id);
        if (!$chatModel) {
            return null;
        }
        return $chatModel->toEntity();
    }

    /**
     * Gets the list of chats for a user with pagination
     * 
     * @param ChatUser $user The user to get chats for
     * @param int $page Page number (default: 1)
     * @param int $perPage Items per page (default: 20)
     * @return \App\Domain\Entities\Chats Returns a domain entity containing:
     *   - chats: array<\App\Domain\Entities\Chat> List of chat entities
     *   - pagination information embedded in the entity
     * 
     * @throws \App\Domain\Exceptions\ChatNotFoundException When no chats are found
     * @throws \App\Domain\Exceptions\InvalidPaginationException When pagination parameters are invalid
     * 
     * @example
     * $chats = $repository->getUserChats($user, 1, 20);
     * $chatsList = $chats->chats; // Chat[]
     * $total = $chats->getTotal(); // int
     * $dto = $chats->toDto(); // ChatListResponseDto for API
     */
    public function getUserChats(ChatUser $user, int $page = 1, int $perPage = 20): \App\Domain\Entities\Chats
    {
        $paginator = ChatModel::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->getId())->where('user_type', $user->getType());
        })
        ->with(['messages' => function ($query) {
            $query->latest()->limit(1);
        }, 'users'])
        ->orderBy('updated_at', 'desc')
        ->paginate($perPage, ['*'], 'page', $page);


        $chats = $paginator->items();
        $chatEntities = array_map(function ($chatModel) use ($user) {
            return $chatModel->toEntityFromReciever($user);
        }, $chats);

        return new \App\Domain\Entities\Chats(
            chats: $chatEntities,
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            lastPage: $paginator->lastPage(),
            from: $paginator->firstItem(),
            to: $paginator->lastItem()
        );
    }

    public function createGroupChat(string $name, string $description, ChatUser $createdBy): Chat
    {
        $chatModel = ChatModel::create([
            'name' => $name,
            'type' => 'group',
            'description' => $description,
            'created_by' => $createdBy->getId(),
        ]);

        return new Chat(
            id: $chatModel->id,
            name: $chatModel->name,
            type: $chatModel->type,
            description: $chatModel->description,
            createdBy: $chatModel->created_by,
            createdByType: $chatModel->created_by_type,
            createdAt: $chatModel->created_at,
            updatedAt: $chatModel->updated_at
        );
    }

    public function addParticipantToChat(int $chatId, ChatUser $user): void
    {
        $chatModel = ChatModel::find($chatId);
        if ($chatModel) {
            $chatModel->addParticipant($user);
        }
    }

    public function removeParticipantFromChat(int $chatId, ChatUser $user): void
    {
        $chatModel = ChatModel::find($chatId);
        if ($chatModel) {
            $chatModel->removeParticipant($user);
        }
    }

    public function markChatAsReadForUser(int $chatId, ChatUser $user): void
    {
        $chatModel = ChatModel::find($chatId);
        if ($chatModel) {
            $chatModel->markAsReadForChatUser($user);
        }
    }

    public function getUnreadCount(ChatUser $user): int
    {
        return ChatModel::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->getId())->where('user_type', $user->getType());
        })
        ->whereHas('messages', function ($query) use ($user) {
            $query->where('sender_id', '!=', $user->getId())->where('is_read', false);
        })
        ->count();
    }

    public function hasParticipant(int $chatId, ChatUser $user): bool
    {
        $chatModel = ChatModel::find($chatId);
        if (!$chatModel) {
            return false;
        }

        return $chatModel->hasParticipant($user);
    }

    public function hasAssistant(int $chatId): bool
    {
        return ChatModel::where('id', $chatId)
            ->whereHas('users', function ($query) {
                $query->where('user_type', 'assistant');
            })
            ->exists();
    }
} 