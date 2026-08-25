<?php

namespace App\Application\UseCases\Template;

use App\Domain\Entities\Template;
use App\Domain\Repositories\TemplateRepositoryInterface;

class CreateTemplateUseCase
{
    public function __construct(
        private TemplateRepositoryInterface $templateRepository,
    ) {}

    public function execute(
        string $name,
        string $type,
        string $bodyFormat,
        ?string $body,
        ?string $subject = null,
        ?string $description = null,
        bool $isActive = true,
        array $options = [],
        ?string $locale = null,
        // Set when creating ANOTHER LANGUAGE of an existing template: the new
        // row joins that group instead of starting one of its own. Null (the
        // normal create) lets the repository open a fresh group.
        ?string $translationGroupId = null,
    ): Template {
        return $this->templateRepository->create(
            name: $name,
            type: $type,
            bodyFormat: $bodyFormat,
            body: $body,
            subject: $subject,
            description: $description,
            isActive: $isActive,
            options: $options,
            locale: $locale,
            translationGroupId: $translationGroupId,
        );
    }
}
