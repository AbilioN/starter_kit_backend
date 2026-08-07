<?php

namespace App\Application\UseCases\Template;

use App\Domain\Exceptions\FileNotFoundException;
use App\Domain\Repositories\FileRepositoryInterface;

class DeleteTemplateBackgroundUseCase
{
    public function __construct(
        private FileRepositoryInterface $fileRepository,
    ) {}

    public function execute(string $templateId, string $fileId): void
    {
        $file = $this->fileRepository->findById($fileId);

        if (! $file || $file->uploadableType !== 'App\\Models\\Template' || (string) $file->uploadableId !== $templateId) {
            throw new FileNotFoundException("File {$fileId} not found on template {$templateId}.");
        }

        $this->fileRepository->delete($fileId);
    }
}
