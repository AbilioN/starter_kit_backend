<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\ChatUser;
use App\Domain\Entities\Message;
use App\Domain\Repositories\MessageRepositoryInterface;
use App\Models\Message as MessageModel;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(string $chatId, string $content, ChatUser $sender, string $messageType = 'text', ?array $metadata = null, ?string $replyToId = null): Message
    {
        $messageModel = MessageModel::create([
            'chat_id' => $chatId,
            'content' => $content,
            'sender_id' => $sender->getId(),
            'sender_type' => $sender->getType(),
            'message_type' => $messageType,
            'metadata' => $metadata,
            'is_read' => false,
            'reply_to_id' => $replyToId,
        ]);
        return $messageModel->toEntity();
    }

    public function findById(string $id): ?Message
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

    public function getChatMessages(string $chatId, int $page = 1, int $perPage = 50): array
    {
        $paginator = MessageModel::with(['sender'])
            ->where('chat_id', $chatId)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        $messages = $paginator->items();
        $messageEntities = array_map(function ($messageModel) {
            $sender = $messageModel->sender ?? $messageModel->senderAdmin;

            $reply = null;
            if ($messageModel->reply_to_id) {
                $parent = MessageModel::find($messageModel->reply_to_id);
                if ($parent) {
                    $reply = [
                        'id'      => $parent->id,
                        'content' => $parent->deleted_at ? null : $parent->content,
                        'sender_id' => $parent->sender_id,
                    ];
                }
            }

            return [
                'id'          => $messageModel->id,
                'chat_id'     => $messageModel->chat_id,
                'content'     => $messageModel->content,
                'sender_type' => $messageModel->sender_type,
                'sender_id'   => $messageModel->sender_id,
                'sender_name' => $sender ? $sender->name : 'Usuário',
                'is_read'     => (bool) $messageModel->is_read,
                'read_at'     => $messageModel->read_at?->format('Y-m-d H:i:s'),
                'edited_at'   => $messageModel->edited_at?->format('Y-m-d H:i:s'),
                'reply_to_id' => $messageModel->reply_to_id,
                'reply'       => $reply,
                'created_at'  => $messageModel->created_at->format('Y-m-d H:i:s'),
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

    public function markAsRead(string $messageId): void
    {
        MessageModel::where('id', $messageId)->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    public function getUnreadCount(string $chatId, ChatUser $user): int
    {
        return MessageModel::where('chat_id', $chatId)
            ->where('sender_id', '!=', $user->getId())
            ->where('is_read', false)
            ->count();
    }
} 