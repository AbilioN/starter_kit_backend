<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\File;

interface FileRepositoryInterface
{
    public function findById(int $id): ?File;
    public function findForUploadable(string $type, int $id, ?string $folder = null, int $perPage = 20): array;
    public function create(array $data): File;
    public function delete(int $id): void;
    public function findAllPaginated(int $page = 1, int $perPage = 20, ?string $folder = null): array;
}
