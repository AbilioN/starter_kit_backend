<?php

namespace App\Application\UseCases\Template;

use App\Application\Services\TemplateFieldCatalogService;
use App\Domain\Repositories\TemplateRepositoryInterface;

/**
 * Reports what a template body refers to and whether those things exist.
 *
 * The problem this solves: an unknown placeholder is not an error anywhere in
 * the pipeline. PlaceholderResolverService::substituteFields() returns '' for
 * a key it does not recognise, so {frist_name} renders as nothing at all and
 * the e-mail goes out with a hole where the name should be. Nobody finds out
 * until a customer does.
 *
 * Deliberately advisory, not blocking. Saving is not the moment to refuse a
 * body — an author moves text around, saves a half-finished template, comes
 * back tomorrow. What matters is that the editor can SHOW the problem while
 * it is still cheap to fix, so this returns findings and the caller decides.
 *
 * The three placeholder families are separated because they fail differently:
 *
 *   {field}         unknown = silently empty. The dangerous one.
 *   {field!}        strict — also aborts the render at send time if empty.
 *   {prompt:name}   supplied per send by the calling code, so there is no
 *                   catalogue to check it against; only reported, never
 *                   flagged.
 *   {@template-id}  an include; flagged when the template does not exist,
 *                   because an include that resolves to nothing is
 *                   indistinguishable from one that was never written.
 */
class ValidateTemplateBodyUseCase
{
    public function __construct(
        private TemplateFieldCatalogService $catalog,
        private TemplateRepositoryInterface $templateRepository,
    ) {}

    /**
     * @return array{
     *     used: array<int, string>,
     *     unknown: array<int, string>,
     *     strict: array<int, string>,
     *     prompts: array<int, string>,
     *     missing_includes: array<int, string>
     * }
     */
    public function execute(?string $body, ?string $subject = null): array
    {
        $source = ($subject ?? '')."\n".($body ?? '');

        $known = $this->catalog->knownKeys();

        preg_match_all('/\{([a-zA-Z0-9_]+)(!?)\}/', $source, $matches, PREG_SET_ORDER);

        $used = [];
        $unknown = [];
        $strict = [];

        foreach ($matches as $match) {
            [, $name, $bang] = $match;

            $used[$name] = true;

            if ($bang === '!') {
                $strict[$name] = true;
            }

            if (! in_array($name, $known, true)) {
                $unknown[$name] = true;
            }
        }

        preg_match_all('/\{prompt:([a-zA-Z0-9_]+)\}/', $source, $promptMatches);

        preg_match_all('/\{@([^}]+)\}/', $source, $includeMatches);

        $missingIncludes = [];

        foreach (array_unique($includeMatches[1] ?? []) as $includedId) {
            if (! $this->templateRepository->findById($includedId)) {
                $missingIncludes[] = $includedId;
            }
        }

        return [
            'used' => array_keys($used),
            'unknown' => array_keys($unknown),
            'strict' => array_keys($strict),
            'prompts' => array_values(array_unique($promptMatches[1] ?? [])),
            'missing_includes' => $missingIncludes,
        ];
    }
}
