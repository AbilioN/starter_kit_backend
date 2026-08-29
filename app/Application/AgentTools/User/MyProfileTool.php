<?php

namespace App\Application\AgentTools\User;

use App\Application\UseCases\User\GetUserProfileUseCase;
use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolResult;

final class MyProfileTool implements SelfScopedTool
{
    public function __construct(private GetUserProfileUseCase $getUserProfile) {}

    public function name(): string
    {
        return 'my_profile';
    }

    public function description(): string
    {
        return "Read the signed-in person's own profile: name, e-mail, whether their e-mail is verified, and when they were last seen.";
    }

    public function parameters(): array
    {
        // No arguments at all. There is nothing to ask for: the only profile
        // this tool can reach is the actor's own.
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
        // The use case answers in the HTTP envelope the controller needs
        // (`success` + `data`). Unwrapped here: an envelope is noise to a model,
        // and every token of it is one the answer does not get.
        $profile = $this->getUserProfile->execute($context->actorId);

        return AgentToolResult::scalar($profile['data'] ?? $profile);
    }
}
