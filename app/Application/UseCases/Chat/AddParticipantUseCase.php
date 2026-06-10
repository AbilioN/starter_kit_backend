<?php

namespace App\Application\UseCases\Chat;

use App\Domain\Entities\ChatUser;
use App\Domain\Entities\ChatUserFactory;
use App\Models\Chat;

class AddParticipantUseCase
{
    public function execute(ChatUser $requester, string $chatId, string $userId, string $userType): array
    {
        $chat = Chat::findOrFail($chatId);

        if (!$chat->hasParticipant($requester)) {
            throw new \Exception('Access denied', 403);
        }

        if ($chat->type !== 'group') {
            throw new \Exception('Can only add participants to group chats', 422);
        }

        $newParticipant = ChatUserFactory::createFromChatUserData($userId, $userType);

        if ($chat->hasParticipant($newParticipant)) {
            throw new \Exception('User is already a participant', 422);
        }

        $chat->users()->attach($userId, [
            'user_type' => $userType,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return [
            'success' => true,
            'data'    => ['message' => 'Participant added successfully'],
        ];
    }
}
