<?php

namespace App\Application\UseCases\Template;

use App\Domain\Repositories\TemplateRepositoryInterface;

class DeleteTemplateUseCase
{
    public function __construct(
        private TemplateRepositoryInterface $templateRepository,
    ) {}

    public function execute(string $id): void
    {
        $this->templateRepository->delete($id);
    }
}
