<?php

namespace App\Application\AgentTools\User;

use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\AgentToolResult;
use App\Models\AgentDocument;

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
        $query = trim((string) $arguments['query']);

        $documents = AgentDocument::query()
            ->where('is_active', true)
            // LIKE with escaped wildcards: a query containing % or _ must be a
            // literal search, not a pattern the model can widen.
            ->where('content', 'like', '%'.addcslashes($query, '%_\\').'%')
            ->limit($context->maxRows)
            ->get(['title', 'content']);

        $rows = [];

        foreach ($documents as $document) {
            foreach ($this->excerpts($document->content, $query) as $excerpt) {
                $rows[] = ['document' => $document->title, 'excerpt' => $excerpt];
            }
        }

        return AgentToolResult::rows($rows, $context->maxRows);
    }

    /**
     * Up to two passages per document. More than that from one source starts
     * crowding out the other documents that also matched, which is usually
     * where the better answer is.
     *
     * @return array<int, string>
     */
    private function excerpts(string $content, string $query): array
    {
        $excerpts = [];
        $offset = 0;

        while (count($excerpts) < 2) {
            $position = mb_stripos($content, $query, $offset);

            if ($position === false) {
                break;
            }

            $start = max(0, $position - self::EXCERPT_RADIUS);
            $excerpt = trim(mb_substr($content, $start, self::EXCERPT_RADIUS * 2));

            $excerpts[] = ($start > 0 ? '…' : '').$excerpt.'…';
            $offset = $position + mb_strlen($query);
        }

        return $excerpts;
    }
}
