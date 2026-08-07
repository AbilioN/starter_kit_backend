<?php

namespace App\Domain\Services;

use App\Domain\ValueObjects\TemplateEntry;

interface PdfRenderServiceInterface
{
    /**
     * Kind 3, no background — the body *is* the document. $html has
     * already had its {placeholders} resolved by PlaceholderResolverService.
     */
    public function renderDocument(string $html): string;

    /**
     * Kind 3, with background(s) — "underlay" mode. Imports page 1 of each
     * file in $backgroundFilePaths (already in the correct order) and
     * stamps every TemplateEntry whose `page` matches that position.
     *
     * $values is the same substitution map PlaceholderResolverService
     * builds (keyed with braces, e.g. '{last_name}' => 'Sample') — needed
     * here too because an entry's own `text` can itself contain
     * {placeholders}, and `if` conditions reference it directly.
     *
     * $isPreview controls whether `highlight` entries draw in red — that
     * attribute is preview-only by definition and must never affect real
     * output.
     *
     * @param string[] $backgroundFilePaths
     * @param TemplateEntry[] $entries
     * @param array<string, string> $values
     */
    public function renderUnderlay(array $backgroundFilePaths, array $entries, array $values, bool $isPreview = false): string;
}
