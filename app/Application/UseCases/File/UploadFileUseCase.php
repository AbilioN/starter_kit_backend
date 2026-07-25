<?php

namespace App\Application\UseCases\File;

use App\Domain\Repositories\FileRepositoryInterface;
use App\Domain\Services\StorageServiceInterface;
use Illuminate\Http\UploadedFile;

class UploadFileUseCase
{
    public function __construct(
        private FileRepositoryInterface $fileRepository,
        private StorageServiceInterface $storageService,
    ) {}

    public function execute(
        UploadedFile $file,
        string $folder = 'uploads',
        string $disk = 'local',
        bool $isPublic = false,
        ?string $uploadableType = null,
        int|string|null $uploadableId = null,
        ?array $meta = null,
    ): array {
        $stored = $this->storageService->store($file, $folder, $disk);

        $entity = $this->fileRepository->create([
            'original_name' => $stored['original_name'],
            'stored_name' => $stored['stored_name'],
            'disk' => $stored['disk'],
            'path' => $stored['path'],
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
            'folder' => $folder,
            'is_public' => $isPublic,
            'uploadable_type' => $uploadableType,
            'uploadable_id' => $uploadableId,
            'meta' => $meta,
        ]);

        $url = $this->storageService->url($entity->path, $entity->disk);
        return $entity->toDto($url)->toArray();
    }
}
