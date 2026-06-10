<?php

namespace App\Application\UseCases\Chat;

use App\Domain\Entities\ChatUser;
use App\Models\Chat;

class RenameChatUseCase
{
    public function execute(ChatUser $user, string $chatId, string $name): array
    {
        $chat = Chat::findOrFail($chatId);

        if (!$chat->hasParticipant($user)) {
            throw new \Exception('Access denied', 403);
        }

        if ($chat->type !== 'group') {
            throw new \Exception('Can only rename group chats', 422);
        }

        $chat->update(['name' => $name]);

        return [
            'success' => true,
            'data'    => ['id' => $chat->id, 'name' => $chat->name],
        ];
    }
}
