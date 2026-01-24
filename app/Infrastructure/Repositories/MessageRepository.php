<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\ChatUser;
use App\Domain\Entities\Message;
use App\Domain\Repositories\MessageRepositoryInterface;
use App\Models\Message as MessageModel;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(int $chatId, string $content, ChatUser $sender, string $messageType = 'text', ?array $metadata = null): Message
    {
        $messageModel = MessageModel::create([
            'chat_id' => $chatId,
            'content' => $content,
            'sender_id' => $sender->getId(),
            'sender_type' => $sender->getType(),
            'message_type' => $messageType,
            'metadata' => $metadata,
            'is_read' => false,
        ]);
        return $messageModel->toEntity();
    }

    public function findById(int $id): ?Message
    {
        $messageModel = MessageModel::find($id);
        if (!$messageModel) {
            return null;
        }
        $sender = ChatUserFactory::createFromChatUserData(
            $messageModel->sender_id,
            $messageModel->sender_type ?? 'user'
        );

        return new Message(
            id: $messageModel->id,
            chatId: $messageModel->chat_id,
            content: $messageModel->content,
            sender: $sender,
            messageType: $messageModel->message_type,
            metadata: $messageModel->metadata,
            isRead: (bool) $messageModel->is_read,
            readAt: $messageModel->read_at,
            createdAt: $messageModel->created_at,
            updatedAt: $messageModel->updated_at
        );
    }

    public function getChatMessages(int $chatId, int $page = 1, int $perPage = 50): array
    {
        $paginator = MessageModel::with(['sender'])
            ->where('chat_id', $chatId)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        $messages = $paginator->items();
        $messageEntities = array_map(function ($messageModel) {
            $sender = $messageModel->sender ?? $messageModel->senderAdmin;
            
            return [
                'id' => $messageModel->id,
                'chat_id' => $messageModel->chat_id,
                'content' => $messageModel->content,
                'sender_type' => $messageModel->sender_type,
                'sender_id' => $messageModel->sender_id,
                'sender_name' => $sender ? $sender->name : 'Usuário',
                'is_read' => (bool) $messageModel->is_read,
                'read_at' => $messageModel->read_at?->format('Y-m-d H:i:s'),
                'created_at' => $messageModel->created_at->format('Y-m-d H:i:s'),
            ];
        }, $messages);

        return [
            'messages' => $messageEntities,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem()
            ]
        ];
    }

    public function markAsRead(int $messageId): void
    {
        MessageModel::where('id', $messageId)->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    public function getUnreadCount(int $chatId, ChatUser $user): int
    {
        return MessageModel::where('chat_id', $chatId)
            ->where('sender_id', '!=', $user->getId())
            ->where('is_read', false)
            ->count();
    }
} 