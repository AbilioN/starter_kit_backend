<?php

namespace App\Application\AgentTools\User;

use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\AgentToolResult;
use App\Application\UseCases\AgentDocument\SearchAgentDocumentsUseCase;

/**
 * Finds passages in the tenant's published documents.
 *
 * **This is keyword retrieval, not embeddings, and that is a deliberate
 * choice.** The model does the understanding; this tool's job is only to put
 * the relevant paragraphs in front of it. A vector store would answer fuzzier
 * questions, and is a real option later — but it is a piece of infrastructure
 * to run and pay for, and `content` is the seam where it would slot in without
 * a single tool changing shape.
 *
 * Returns EXCERPTS rather than whole documents: a manual pasted whole would
 * swallow the result cap and crowd out everything else the turn needed.
 */
final class SearchDocumentsTool implements AgentToolInterface
{
    public function __construct(private SearchAgentDocumentsUseCase $search) {}

    /** Characters of context returned either side of a match. */
    private const EXCERPT_RADIUS = 320;

    public function name(): string
    {
        return 'search_documents';
    }

    public function description(): string
    {
        return "Search inside the workspace's published documents and return the passages that mention a term. Prefer this over guessing: it returns the document's own words.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'minLength' => 2,
                    'maxLength' => 120,
                    'description' => 'A word or short phrase to look for.',
                ],
            ],
            'required' => ['query'],
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
        return AgentToolResult::rows(
            $this->search->excerpts((string) $arguments['query'], $context->actorType, $context->maxRows),
            $context->maxRows,
        );
    }
}
