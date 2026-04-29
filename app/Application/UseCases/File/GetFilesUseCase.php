<?php

namespace App\Application\UseCases\File;

use App\Domain\Repositories\FileRepositoryInterface;
use App\Domain\Services\StorageServiceInterface;

class GetFilesUseCase
{
    public function __construct(
        private FileRepositoryInterface $fileRepository,
        private StorageServiceInterface $storageService,
    ) {}

    public function execute(int $page = 1, int $perPage = 20, ?string $folder = null): array
    {
        $result = $this->fileRepository->findAllPaginated($page, $perPage, $folder);
        $result['data'] = array_map(
            fn($entity) => $entity->toDto($this->storageService->url($entity->path, $entity->disk))->toArray(),
            $result['data']
        );
        return $result;
    }

    public function forUploadable(string $type, int $id, ?string $folder = null, int $perPage = 20): array
    {
        $result = $this->fileRepository->findForUploadable($type, $id, $folder, $perPage);
        $result['data'] = array_map(
            fn($entity) => $entity->toDto($this->storageService->url($entity->path, $entity->disk))->toArray(),
            $result['data']
        );
        return $result;
    }
}
