<?php

namespace App\Application\AgentTools;

use App\Application\UseCases\User\CountUsersUseCase;
use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\AgentToolResult;

/**
 * The first real tool. Read-only, wraps an existing use case, and declares the
 * permission the ACTOR must hold rather than checking anything itself — the
 * executor owns tenant connection, authorization, validation, capping and
 * auditing precisely so a handler cannot forget one.
 */
final class CountUsersTool implements AgentToolInterface
{
    public function __construct(private CountUsersUseCase $countUsers) {}

    public function name(): string
    {
        return 'count_users';
    }

    public function description(): string
    {
        return 'Count the users in this workspace, optionally filtered by the date they signed up.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'created_after' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Only count users who signed up on or after this date (YYYY-MM-DD).',
                ],
                'created_before' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Only count users who signed up on or before this date (YYYY-MM-DD).',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function permission(): ?string
    {
        return 'user-read';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(array $arguments, AgentToolContext $context): AgentToolResult
    {
        return AgentToolResult::scalar([
            'count' => $this->countUsers->execute(
                $arguments['created_after'] ?? null,
                $arguments['created_before'] ?? null,
            ),
        ]);
    }
}
