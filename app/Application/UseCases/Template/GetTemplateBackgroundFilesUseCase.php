<?php

namespace App\Application\UseCases\Template;

use App\Domain\Repositories\FileRepositoryInterface;

class GetTemplateBackgroundFilesUseCase
{
    public function __construct(
        private FileRepositoryInterface $fileRepository,
    ) {}

    /**
     * @return \App\Domain\Entities\File[] Ordered by `meta.sort` — the
     *   actual page order. Sorted in PHP, not SQL, to avoid a JSON-path
     *   query that isn't portable across the sqlite/mysql split this app
     *   already runs on (same reasoning as the financial report's monthly
     *   revenue breakdown).
     */
    public function execute(string $templateId): array
    {
        $result = $this->fileRepository->findForUploadable('App\\Models\\Template', $templateId, 'pdf_background', 100);

        $files = $result['data'];
        usort($files, fn ($a, $b) => ($a->meta['sort'] ?? 0) <=> ($b->meta['sort'] ?? 0));

        return $files;
    }
}
