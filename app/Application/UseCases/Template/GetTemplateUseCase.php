<?php

namespace App\Application\UseCases\Template;

use App\Domain\Entities\Template;
use App\Domain\Exceptions\TemplateNotFoundException;
use App\Domain\Repositories\TemplateRepositoryInterface;

class GetTemplateUseCase
{
    public function __construct(
        private TemplateRepositoryInterface $templateRepository,
    ) {}

    public function execute(string $id): Template
    {
        $template = $this->templateRepository->findById($id);

        if (! $template) {
            throw new TemplateNotFoundException("Template {$id} not found.");
        }

        return $template;
    }
}
