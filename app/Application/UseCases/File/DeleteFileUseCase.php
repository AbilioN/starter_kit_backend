<?php

namespace App\Application\UseCases\File;

use App\Domain\Exceptions\FileNotFoundException;
use App\Domain\Repositories\FileRepositoryInterface;
use App\Domain\Services\StorageServiceInterface;

class DeleteFileUseCase
{
    public function __construct(
        private FileRepositoryInterface $fileRepository,
        private StorageServiceInterface $storageService,
    ) {}

    public function execute(string $id): void
    {
        $entity = $this->fileRepository->findById($id);

        if (!$entity) {
            throw new FileNotFoundException("File #{$id} not found.");
        }

        $this->storageService->delete($entity->path, $entity->disk);
        $this->fileRepository->delete($id);
    }
}
