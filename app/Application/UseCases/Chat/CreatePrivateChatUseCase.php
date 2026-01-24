<?php

namespace App\Application\UseCases\Chat;

use App\Domain\Entities\Chat;
use App\Domain\Entities\ChatUser;
use App\Domain\Repositories\ChatRepositoryInterface;

class CreatePrivateChatUseCase
{
    public function __construct(private ChatRepositoryInterface $chatRepository) {}

    public function execute(ChatUser $user1, ChatUser $user2): Chat
    {
        $chatData = $this->chatRepository->findOrCreatePrivateChat($user1, $user2);
        return $chatData;
    }
} 