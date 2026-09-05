<?php

namespace App\Application\AgentTools;

use App\Application\UseCases\AgentDocument\SearchAgentDocumentsUseCase;
use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\AgentToolResult;
use App\Domain\AgentTools\Exceptions\AgentToolFailure;
use App\Models\Admin;

/**
 * The workspace's own documents, for the people who run it.
 *
 * There is a sibling of this class under `AgentTools\User\` and the difference
 * between them is the whole reason both exist: **the user one declares no
 * permission.** That is right for an end user, who holds none — but registering
 * it admin-side would have meant `authorizeActor()` returning early on a null
 * slug, so an admin's document search would run with **no permission check and
 * no actor resolution at all**. Two thin classes over one shared use case is
 * the price of not having that hole.
 *
 * An admin sees `internal` documents as well as `published` ones; the audience
 * rule lives in `AgentDocument::scopeReadableBy()`, driven by the grant's actor
 * type, so neither tool can forget it.
 *
 * `query` is optional on purpose: without one this answers "what can be
 * consulted", which is the question a model should ask first and which used to
 * need a second tool.
 */
final class SearchDocumentsTool implements AgentToolInterface
{
    public function __construct(private SearchAgentDocumentsUseCase $search) {}

    public function name(): string
    {
        return 'search_documents';
    }

    public function description(): string
    {
        return "Search inside this workspace's own documents — manuals, policies, price lists, "
            .'internal rules — and return the passages that mention a term, in the document\'s own '
            .'words. Omit `query` to list what documents exist first. Prefer this over answering '
            .'from general knowledge: these are this business\'s own rules and are not knowable '
            .'otherwise.';
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
                    'description' => 'A word or phrase to find. Omit to list the available documents instead.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function permission(): ?string
    {
        // Reading, never `document-manage` — that one uploads files and decides
        // whether a document is visible to every end user.
        return 'document-read';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(array $arguments, AgentToolContext $context): AgentToolResult
    {
        $actor = $context->actorType === 'admin' ? Admin::find($context->actorId) : null;

        if ($actor === null) {
            // Fail CLOSED: this handler is the one that can read `internal`
            // documents, and it decides that from the actor type alone.
            throw AgentToolFailure::permissionDenied($this->permission());
        }

        $query = trim((string) ($arguments['query'] ?? ''));

        $rows = $query === ''
            ? $this->search->catalogue($context->actorType, $context->maxRows)
            : $this->search->excerpts($query, $context->actorType, $context->maxRows);

        return AgentToolResult::rows($rows, $context->maxRows);
    }
}
