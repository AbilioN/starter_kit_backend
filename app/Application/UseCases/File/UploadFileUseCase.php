<?php

namespace App\Application\UseCases\File;

use App\Domain\Exceptions\PlanLimitExceededException;
use App\Domain\Repositories\FileRepositoryInterface;
use App\Domain\Services\StorageServiceInterface;
use App\Helpers\Settings;
use App\Models\File;
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
        $this->enforceStorageLimit($file->getSize());

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

    /**
     * Byte-sum based, unlike EnforcePlanLimitUseCase's discrete counts -
     * `limits.max_storage_mb` is stored in MB (matches the plan form's
     * unit), so the comparison converts it to bytes here rather than
     * forcing that unit conversion into the generic count-based use case.
     */
    private function enforceStorageLimit(int $incomingBytes): void
    {
        $limitMb = Settings::get('limits.max_storage_mb');

        if ($limitMb === null) {
            return;
        }

        $limitBytes = (int) $limitMb * 1024 * 1024;
        $projectedBytes = (int) File::sum('size') + $incomingBytes;

        if ($projectedBytes > $limitBytes) {
            throw new PlanLimitExceededException(
                "This upload would exceed this tenant's plan storage limit of {$limitMb} MB."
            );
        }
    }
}
