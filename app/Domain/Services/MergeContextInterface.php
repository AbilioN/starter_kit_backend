<?php

namespace App\Domain\Services;

/**
 * The seam that lets the templates module be built and tested standalone,
 * before any business entity (User, Chat, whatever eventually gets merged
 * into a template) exists. Nothing in the rendering pipeline knows where
 * these strings come from — implement this once per entity when that layer
 * lands, and nothing else in the module needs to change.
 */
interface MergeContextInterface
{
    /**
     * Catalogue for the authoring UI (field picker, "insert field" buttons).
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function fields(): array;

    /**
     * Substitution map for one record.
     *
     * @return array<string, string> e.g. ['{first_name}' => 'Jean', ...]
     */
    public function values(int|string $recordId): array;
}
