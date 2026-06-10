<?php

namespace App\Application\UseCases\Chat;

use App\Domain\Entities\ChatUser;
use App\Events\MessageDeleted;
use App\Models\Chat;
use App\Models\Message;

class DeleteMessageUseCase
{
    public function execute(ChatUser $user, string $chatId, string $messageId): void
    {
        $chat = Chat::findOrFail($chatId);

        if (!$chat->hasParticipant($user)) {
            throw new \Exception('Access denied', 403);
        }

        $message = Message::where('id', $messageId)
            ->where('chat_id', $chatId)
            ->firstOrFail();

        if ($message->sender_id !== $user->getId()) {
            throw new \Exception('You can only delete your own messages', 403);
        }

        $message->delete(); // soft delete

        broadcast(new MessageDeleted(
            messageId: $messageId,
            chatId: $chatId,
        ))->toOthers();
    }
}
