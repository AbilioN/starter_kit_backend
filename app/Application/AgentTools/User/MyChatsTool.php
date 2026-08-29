<?php

namespace App\Application\AgentTools\User;

use App\Application\UseCases\Chat\GetChatsUseCase;
use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolResult;
use App\Domain\Entities\ChatUserFactory;

final class MyChatsTool implements SelfScopedTool
{
    public function __construct(private GetChatsUseCase $getChats) {}

    public function name(): string
    {
        return 'my_chats';
    }

    public function description(): string
    {
        return 'List the conversations the signed-in person takes part in.';
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
        // The actor, and only the actor: getUserChats() scopes by this
        // participant, so there is no chat here the person is not in.
        $actor = ChatUserFactory::createFromChatUserData($context->actorId, 'user');
        $chats = $this->getChats->execute($actor, 1, $context->maxRows)->toDto()->toArray();

        $rows = array_map(fn (array $chat) => [
            'id' => $chat['id'] ?? null,
            'name' => $chat['name'] ?? null,
            'type' => $chat['type'] ?? null,
        ], $chats['chats'] ?? []);

        return AgentToolResult::rows($rows, $context->maxRows);
    }
}
