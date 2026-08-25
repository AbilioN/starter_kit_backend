<?php

namespace App\Application\UseCases\Template;

use App\Domain\Entities\Template;
use App\Domain\Exceptions\TemplateNotFoundException;
use App\Domain\Repositories\TemplateRepositoryInterface;

/**
 * Every language of one template, given any one of them.
 *
 * The editor needs this to draw its language tabs: a tab per language the
 * tenant runs, showing which are written and which are still empty. Without
 * it the tabs could only be built by paging the whole template list and
 * grouping client-side, which stops working at the first page boundary.
 *
 * Falls back to the row itself when it has no group — a template created
 * before translation groups existed, or one restored from an old backup.
 * Returning nothing there would make its own editor claim the template does
 * not exist.
 *
 * @return array<int, Template>
 */
class GetTemplateTranslationsUseCase
{
    public function __construct(
        private TemplateRepositoryInterface $templateRepository,
    ) {}

    public function execute(string $templateId): array
    {
        $template = $this->templateRepository->findById($templateId);

        if (! $template) {
            throw new TemplateNotFoundException("Template {$templateId} not found.");
        }

        if ($template->translationGroupId === null) {
            return [$template];
        }

        return $this->templateRepository->findTranslationGroup($template->translationGroupId);
    }
}
