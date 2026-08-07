<?php

namespace App\Application\Services;

use App\Domain\Exceptions\StrictFieldEmptyException;
use App\Domain\Repositories\TemplateRepositoryInterface;
use App\Domain\Services\MergeContextInterface;

/**
 * Resolves the placeholder language over a template body. Pure aside from
 * MergeContextInterface/TemplateRepositoryInterface calls — no PDF/mail/SMS
 * concerns here, which is what makes it unit-testable in isolation.
 *
 * Order matters and is fixed: expand includes, THEN validate strict fields,
 * THEN substitute. Validating strict fields before expanding includes would
 * let a `{field!}` inside an included block escape validation.
 *
 * Deliberately not implemented yet (depend on a real MergeContext entity or
 * additional PDF layout concerns not core to this module): `{mainheader}`/
 * `{mainfooter}`, `{footer}` (a PdfRenderService layout concern, not a text
 * substitution), `{products_*}`/custom-table variables. `[x]`/`[ ]` are
 * literal characters an author types for checkbox glyphs — nothing to
 * resolve.
 */
class PlaceholderResolverService
{
    private const MAX_INCLUDE_DEPTH = 20;

    public function __construct(
        private MergeContextInterface $mergeContext,
        private TemplateRepositoryInterface $templateRepository,
    ) {}

    /**
     * @param array<string, string> $promptValues Keyed by the bare name inside {prompt:name} — supplied by the caller at generation time, not the record.
     * @param bool $isPreview Fills any catalogue field still empty with «label» (spec §6's preview loop), so an unfilled field is visible on the page rather than just missing.
     */
    public function resolve(string $body, int|string|null $recordId = null, array $promptValues = [], bool $isPreview = false): string
    {
        $body = $this->expandIncludes($body, depth: 0);

        $values = $this->buildValueMap($recordId, $isPreview);

        $this->assertStrictFieldsFilled($body, $values);

        $body = $this->stripStrictMarkers($body);
        $body = $this->substitutePromptValues($body, $promptValues);

        return $this->substituteFields($body, $values);
    }

    private function expandIncludes(string $body, int $depth): string
    {
        if ($depth >= self::MAX_INCLUDE_DEPTH) {
            return $body;
        }

        return preg_replace_callback('/\{@([^}]+)\}/', function (array $matches) use ($depth) {
            $included = $this->templateRepository->findById($matches[1]);

            if (! $included || $included->body === null) {
                return '';
            }

            // Included bodies can themselves include other templates.
            return $this->expandIncludes($included->body, $depth + 1);
        }, $body);
    }

    /**
     * Public so callers that need the raw value map without running the
     * full include/strict/substitute pipeline can reuse it — namely
     * RenderTemplateUseCase's underlay-PDF path, where each TemplateEntry
     * is resolved individually against the same map rather than as one
     * whole-body substitution pass.
     *
     * @return array<string, string>
     */
    public function buildValueMap(int|string|null $recordId, bool $isPreview = false): array
    {
        $values = $recordId !== null ? $this->mergeContext->values($recordId) : [];

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if ($tenant) {
            $values['{company}'] ??= $tenant->name ?? '';
            $values['{company_logo}'] ??= $tenant->logo_path ? asset('storage/'.$tenant->logo_path) : '';
            $values['{company_footer}'] ??= '';
            $values['{company_contact}'] ??= '';
        }

        if ($isPreview) {
            foreach ($this->mergeContext->fields() as $field) {
                $key = '{'.$field['key'].'}';

                if (empty($values[$key])) {
                    $values[$key] = '«'.$field['label'].'»';
                }
            }
        }

        return $values;
    }

    private function assertStrictFieldsFilled(string $body, array $values): void
    {
        if (! preg_match_all('/\{([a-zA-Z0-9_]+)!\}/', $body, $matches)) {
            return;
        }

        $fieldLabels = collect($this->mergeContext->fields())->pluck('label', 'key');

        foreach ($matches[1] as $field) {
            $value = $values['{'.$field.'}'] ?? '';

            if ($value === '' || $value === null) {
                throw new StrictFieldEmptyException($fieldLabels[$field] ?? $field);
            }
        }
    }

    private function stripStrictMarkers(string $body): string
    {
        return preg_replace('/\{([a-zA-Z0-9_]+)!\}/', '{$1}', $body);
    }

    /**
     * @param array<string, string> $promptValues
     */
    private function substitutePromptValues(string $body, array $promptValues): string
    {
        return preg_replace_callback('/\{prompt:([a-zA-Z0-9_]+)\}/', function (array $matches) use ($promptValues) {
            return $promptValues[$matches[1]] ?? '';
        }, $body);
    }

    /**
     * @param array<string, string> $values Keyed WITH braces, e.g. '{first_name}' => 'Jean'.
     */
    private function substituteFields(string $body, array $values): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($values) {
            return $values[$matches[0]] ?? '';
        }, $body);
    }
}
