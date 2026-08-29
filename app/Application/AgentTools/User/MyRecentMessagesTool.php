<?php

namespace App\Application\AgentTools\User;

use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolResult;
use App\Domain\AgentTools\Exceptions\AgentToolFailure;
use App\Domain\Entities\ChatUserFactory;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Application\UseCases\Chat\GetChatMessagesUseCase;

/**
 * The one user tool that accepts an identifier from the model, and therefore
 * the one that carries the rule: **participation is verified before the
 * identifier is used**.
 */
final class MyRecentMessagesTool implements SelfScopedTool
{
    public function __construct(
        private GetChatMessagesUseCase $getChatMessages,
        private ChatRepositoryInterface $chats,
    ) {}

    public function name(): string
    {
        return 'my_recent_messages';
    }

    public function description(): string
    {
        return 'Read recent messages from one of the signed-in person\'s own conversations. Use my_chats first to find the conversation id.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'chat_id' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'maxLength' => 64,
                    'description' => 'Id of a conversation the person takes part in.',
                ],
            ],
            'required' => ['chat_id'],
            'additionalProperties' => false,
        ];
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
        $chatId = (string) $arguments['chat_id'];

        // Checked HERE, before the id reaches anything. GetChatMessagesUseCase
        // checks too, but it signals with a generic exception that would surface
        // as an infrastructure error — the agent should be able to say "that is
        // not your conversation" and carry on, which needs a refusal, not a 500.
        if (! $this->chats->hasParticipant($chatId, $actor)) {
            throw AgentToolFailure::permissionDenied(null);
        }

        $messages = $this->getChatMessages->execute($actor, $chatId, 1, $context->maxRows)->toArray();

        $rows = array_map(fn (array $message) => [
            'content' => $message['content'] ?? null,
            'sender_type' => $message['sender_type'] ?? null,
            'created_at' => $message['created_at'] ?? null,
        ], $messages['messages'] ?? []);

        return AgentToolResult::rows($rows, $context->maxRows);
    }
}
