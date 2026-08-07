<?php

namespace App\Application\UseCases\Template;

use App\Domain\Repositories\TemplateRepositoryInterface;

class GetTemplatesUseCase
{
    public function __construct(
        private TemplateRepositoryInterface $templateRepository,
    ) {}

    public function execute(int $page = 1, int $perPage = 15, ?string $type = null): array
    {
        return $this->templateRepository->findAllPaginated($page, $perPage, $type);
    }
}
