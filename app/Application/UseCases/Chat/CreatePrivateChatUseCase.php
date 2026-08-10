<?php

namespace App\Application\UseCases\Chat;

use App\Domain\Entities\Chat;
use App\Domain\Entities\ChatUser;
use App\Domain\Repositories\ChatRepositoryInterface;

class CreatePrivateChatUseCase
{
    public function __construct(private ChatRepositoryInterface $chatRepository) {}

    public function execute(ChatUser $user1, ChatUser $user2, bool $forceNew = false): Chat
    {
        return $forceNew
            ? $this->chatRepository->createNewPrivateChat($user1, $user2)
            : $this->chatRepository->findOrCreatePrivateChat($user1, $user2);
    }
} 