<?php

namespace App\Application\UseCases\Chat;

use App\Domain\Entities\ChatUser;
use App\Models\Chat;

class LeaveChatUseCase
{
    public function execute(ChatUser $user, string $chatId): array
    {
        $chat = Chat::findOrFail($chatId);

        if (!$chat->hasParticipant($user)) {
            throw new \Exception('Access denied', 403);
        }

        $chat->users()
            ->wherePivot('user_id', $user->getId())
            ->wherePivot('user_type', $user->getType())
            ->detach($user->getId());

        return [
            'success' => true,
            'data'    => ['message' => 'Left chat successfully'],
        ];
    }
}
