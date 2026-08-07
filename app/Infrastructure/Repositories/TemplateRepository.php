<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Template;
use App\Domain\Repositories\TemplateRepositoryInterface;
use App\Models\Template as TemplateModel;

class TemplateRepository implements TemplateRepositoryInterface
{
    public function findById(string $id): ?Template
    {
        return TemplateModel::find($id)?->toEntity();
    }

    public function findAll(?string $type = null): array
    {
        $query = TemplateModel::query();

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('name')->get()
            ->map(fn (TemplateModel $template) => $template->toEntity())
            ->all();
    }

    public function findAllPaginated(int $page = 1, int $perPage = 15, ?string $type = null): array
    {
        $query = TemplateModel::query();

        if ($type) {
            $query->where('type', $type);
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => array_map(fn (TemplateModel $template) => $template->toEntity(), $paginator->items()),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    public function create(
        string $name,
        string $type,
        string $bodyFormat,
        ?string $body,
        ?string $subject = null,
        ?string $description = null,
        bool $isActive = true,
        array $options = [],
    ): Template {
        $template = TemplateModel::create([
            'name' => $name,
            'type' => $type,
            'body_format' => $bodyFormat,
            'body' => $body,
            'subject' => $subject,
            'description' => $description,
            'is_active' => $isActive,
            'options' => $options,
        ]);

        return $template->toEntity();
    }

    public function update(
        string $id,
        ?string $name = null,
        ?string $bodyFormat = null,
        ?string $body = null,
        ?string $subject = null,
        ?string $description = null,
        ?bool $isActive = null,
        ?array $options = null,
    ): Template {
        $template = TemplateModel::findOrFail($id);

        $updateData = array_filter([
            'name' => $name,
            'body_format' => $bodyFormat,
            'body' => $body,
            'subject' => $subject,
            'description' => $description,
            'is_active' => $isActive,
            'options' => $options,
        ], fn ($value) => $value !== null);

        $template->update($updateData);

        return $template->fresh()->toEntity();
    }

    public function delete(string $id): void
    {
        TemplateModel::findOrFail($id)->delete();
    }
}
