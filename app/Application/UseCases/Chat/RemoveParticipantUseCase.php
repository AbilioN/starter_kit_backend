<?php

namespace App\Application\UseCases\Chat;

use App\Domain\Entities\ChatUser;
use App\Models\Chat;

class RemoveParticipantUseCase
{
    public function execute(ChatUser $requester, string $chatId, string $userId, string $userType = 'user'): array
    {
        $chat = Chat::findOrFail($chatId);

        if (!$chat->hasParticipant($requester)) {
            throw new \Exception('Access denied', 403);
        }

        if ($chat->type !== 'group') {
            throw new \Exception('Can only remove participants from group chats', 422);
        }

        $chat->users()
            ->wherePivot('user_id', $userId)
            ->wherePivot('user_type', $userType)
            ->detach($userId);

        return [
            'success' => true,
            'data'    => ['message' => 'Participant removed successfully'],
        ];
    }
}
