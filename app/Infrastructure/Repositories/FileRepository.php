<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\File;
use App\Domain\Repositories\FileRepositoryInterface;
use App\Models\File as FileModel;

class FileRepository implements FileRepositoryInterface
{
    public function findById(int $id): ?File
    {
        $model = FileModel::find($id);
        return $model?->toEntity();
    }

    public function findForUploadable(string $type, int $id, ?string $folder = null, int $perPage = 20): array
    {
        $query = FileModel::where('uploadable_type', $type)->where('uploadable_id', $id);

        if ($folder !== null) {
            $query->where('folder', $folder);
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return [
            'data' => array_map(fn($m) => $m->toEntity(), $paginator->items()),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function create(array $data): File
    {
        return FileModel::create($data)->toEntity();
    }

    public function delete(int $id): void
    {
        FileModel::findOrFail($id)->delete();
    }

    public function findAllPaginated(int $page = 1, int $perPage = 20, ?string $folder = null): array
    {
        $query = FileModel::query();

        if ($folder !== null) {
            $query->where('folder', $folder);
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => array_map(fn($m) => $m->toEntity(), $paginator->items()),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
