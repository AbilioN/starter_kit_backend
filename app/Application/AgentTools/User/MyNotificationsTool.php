<?php

namespace App\Application\AgentTools\User;

use App\Application\UseCases\Notification\GetNotificationsUseCase;
use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolResult;

final class MyNotificationsTool implements SelfScopedTool
{
    /**
     * The notifiable type the user surface writes and reads with. Hardcoded
     * rather than derived from the actor: this tool only ever exists for a user
     * actor, and deriving it would be a place where a wrong value could widen
     * the query.
     */
    private const NOTIFIABLE_TYPE = 'App\Models\User';

    public function __construct(private GetNotificationsUseCase $getNotifications) {}

    public function name(): string
    {
        return 'my_notifications';
    }

    public function description(): string
    {
        return "The signed-in person's own notifications, newest first.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'unread_only' => [
                    'type' => 'boolean',
                    'description' => 'When true, return only notifications that have not been read.',
                ],
            ],
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
        $result = $this->getNotifications->execute(
            self::NOTIFIABLE_TYPE,
            $context->actorId,
            (bool) ($arguments['unread_only'] ?? false),
            $context->maxRows,
        );

        return AgentToolResult::rows(array_values($result['data'] ?? $result['notifications'] ?? []), $context->maxRows);
    }
}
