<?php

namespace App\Application\UseCases\Template;

use App\Domain\Entities\Template;
use App\Domain\Repositories\TemplateRepositoryInterface;

class UpdateTemplateUseCase
{
    public function __construct(
        private TemplateRepositoryInterface $templateRepository,
    ) {}

    public function execute(
        string $id,
        ?string $name = null,
        ?string $bodyFormat = null,
        ?string $body = null,
        ?string $subject = null,
        ?string $description = null,
        ?bool $isActive = null,
        ?array $options = null,
    ): Template {
        return $this->templateRepository->update(
            id: $id,
            name: $name,
            bodyFormat: $bodyFormat,
            body: $body,
            subject: $subject,
            description: $description,
            isActive: $isActive,
            options: $options,
        );
    }
}
