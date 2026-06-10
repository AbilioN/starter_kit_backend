<?php

namespace App\Application\UseCases\Chat;

use App\Domain\Entities\ChatUser;
use App\Events\MessageEdited;
use App\Models\Chat;
use App\Models\Message;

class EditMessageUseCase
{
    public function execute(ChatUser $user, string $chatId, string $messageId, string $newContent): array
    {
        $chat = Chat::findOrFail($chatId);

        if (!$chat->hasParticipant($user)) {
            throw new \Exception('Access denied', 403);
        }

        $message = Message::where('id', $messageId)
            ->where('chat_id', $chatId)
            ->firstOrFail();

        if ($message->sender_id !== $user->getId()) {
            throw new \Exception('You can only edit your own messages', 403);
        }

        $editedAt = now();
        $message->update([
            'content'   => $newContent,
            'edited_at' => $editedAt,
        ]);

        broadcast(new MessageEdited(
            messageId: $messageId,
            chatId: $chatId,
            newContent: $newContent,
            editedAt: $editedAt->format('Y-m-d H:i:s'),
        ))->toOthers();

        return [
            'success' => true,
            'data'    => [
                'id'        => $message->id,
                'content'   => $message->content,
                'edited_at' => $editedAt->format('Y-m-d H:i:s'),
            ],
        ];
    }
}
