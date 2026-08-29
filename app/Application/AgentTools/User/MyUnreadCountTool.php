<?php

namespace App\Application\AgentTools\User;

use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolResult;
use App\Domain\Entities\ChatUserFactory;
use App\Domain\Repositories\ChatRepositoryInterface;

final class MyUnreadCountTool implements SelfScopedTool
{
    public function __construct(private ChatRepositoryInterface $chats) {}

    public function name(): string
    {
        return 'my_unread_count';
    }

    public function description(): string
    {
        return 'How many unread chat messages are waiting for the signed-in person.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(array $arguments, AgentToolContext $context): AgentToolResult
    {
        $actor = ChatUserFactory::createFromChatUserData($context->actorId, 'user');

        return AgentToolResult::scalar(['unread' => $this->chats->getUnreadCount($actor)]);
    }
}
