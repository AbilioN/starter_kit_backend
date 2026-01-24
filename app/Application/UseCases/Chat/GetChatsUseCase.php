<?php

namespace App\Application\UseCases\Chat;

use App\Domain\Entities\ChatUser;
use App\Domain\Repositories\ChatRepositoryInterface;

class GetChatsUseCase
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository
    ) {}

    public function execute(ChatUser $user, int $page = 1, int $perPage = 20): \App\Domain\Entities\Chats
    {
        return $this->chatRepository->getUserChats($user, $page, $perPage);
    }
}
