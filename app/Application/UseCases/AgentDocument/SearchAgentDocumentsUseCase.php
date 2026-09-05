<?php

namespace App\Application\UseCases\AgentDocument;

use App\Models\AgentDocument;

/**
 * Finds passages in the documents an actor is allowed to read.
 *
 * Extracted when the admin side gained its own document tool, so that the
 * audience rule and the excerpt shape live in ONE place. Two tools with the
 * same search written twice is two tools that drift, and the thing they would
 * drift on is which documents an end user may see.
 *
 * **Still keyword retrieval, not embeddings**, and still a deliberate choice —
 * `content` remains the seam where a vector index slots in without a tool
 * changing shape. See `docs/20-tenant-agent-knowledge.md` §4.
 */
class SearchAgentDocumentsUseCase
{
    /** Characters of context returned either side of a match. */
    private const EXCERPT_RADIUS = 320;

    /** Excerpts per document: enough to show the term in use, not the manual. */
    private const EXCERPTS_PER_DOCUMENT = 2;

    /**
     * @return array<int, array{document: string, excerpt: string}>
     */
    public function excerpts(string $query, ?string $actorType, int $maxRows): array
    {
        $query = trim($query);

        $documents = AgentDocument::query()
            ->readableBy($actorType)
            // LIKE with escaped wildcards: a query containing % or _ must be a
            // literal search, not a pattern the model can widen.
            ->where('content', 'like', '%'.addcslashes($query, '%_\\').'%')
            ->limit($maxRows)
            ->get(['title', 'content']);

        $rows = [];

        foreach ($documents as $document) {
            foreach ($this->passages($document->content, $query) as $excerpt) {
                $rows[] = ['document' => $document->title, 'excerpt' => $excerpt];
            }
        }

        return $rows;
    }

    /**
     * What can be consulted, without reading any of it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogue(?string $actorType, int $maxRows): array
    {
        return AgentDocument::query()
            ->readableBy($actorType)
            ->orderBy('title')
            ->limit($maxRows)
            ->get(['title', 'description', 'audience'])
            // `title`/`description` deliberately, not renamed: this is the
            // shape list_documents has shipped with, and a tool's output is a
            // contract even when its only reader is a model.
            ->map(fn (AgentDocument $d) => array_filter([
                'title' => $d->title,
                'description' => $d->description,
                // Only meaningful to an admin, who is the only actor that can
                // see both kinds and may need to know which this is.
                'audience' => $actorType === 'admin' ? $d->audience : null,
            ], fn ($v) => $v !== null && $v !== ''))
            ->all();
    }

    /**
     * Excerpts rather than whole documents: a manual returned whole would
     * swallow the result cap and crowd out everything else the turn needed.
     *
     * @return array<int, string>
     */
    private function passages(string $content, string $query): array
    {
        $excerpts = [];
        $offset = 0;

        while (count($excerpts) < self::EXCERPTS_PER_DOCUMENT) {
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
