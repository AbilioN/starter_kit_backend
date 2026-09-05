<?php

namespace App\Application\AgentTools\User;

use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\AgentToolResult;
use App\Application\UseCases\AgentDocument\SearchAgentDocumentsUseCase;

/**
 * What the tenant has published, so the model knows what it can consult.
 *
 * **Not self-scoped, and deliberately so.** These documents are the tenant's
 * own material, published FOR its users — a different category from another
 * user's data. Every user of the tenant sees the same list, which is the point
 * of publishing it. See docs/15 §6.
 */
final class ListDocumentsTool implements AgentToolInterface
{
    public function __construct(private SearchAgentDocumentsUseCase $search) {}

    public function name(): string
    {
        return 'list_documents';
    }

    public function description(): string
    {
        return 'List the documents this workspace publishes — manuals, policies, FAQs. Use this to find out what can be consulted, then search_documents to read inside them.';
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
        return AgentToolResult::rows(
            $this->search->catalogue($context->actorType, $context->maxRows),
            $context->maxRows,
        );
    }
}
