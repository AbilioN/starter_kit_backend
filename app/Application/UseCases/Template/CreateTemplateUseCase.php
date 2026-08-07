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
        );
    }
}
