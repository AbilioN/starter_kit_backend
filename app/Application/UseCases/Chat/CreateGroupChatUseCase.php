<?php

namespace App\Application\UseCases\Chat;

use App\Domain\Entities\Chat;
use App\Domain\Entities\ChatUser;
use App\Domain\Repositories\ChatRepositoryInterface;

class CreateGroupChatUseCase
{
    public function __construct(private ChatRepositoryInterface $chatRepository) {}

    /**
     * @param ChatUser $creator
     * @param string $name
     * @param string|null $description
     * @param array $participants // Array de ChatUser
     * @return Chat
     */
    public function execute(ChatUser $creator, string $name, ?string $description, array $participants): Chat
    {
        $allParticipants = array_merge($participants, [$creator]);
        $chatEntity = $this->chatRepository->createGroupChat($name, $description ?? '', $creator);
        foreach ($allParticipants as $participant) {
            $this->chatRepository->addParticipantToChat($chatEntity->getId(), $participant);
        }
        return $chatEntity;
    }
} 