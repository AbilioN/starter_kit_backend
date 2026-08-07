<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Template;

interface TemplateRepositoryInterface
{
    public function findById(string $id): ?Template;

    public function findAll(?string $type = null): array;

    public function findAllPaginated(int $page = 1, int $perPage = 15, ?string $type = null): array;

    public function create(
        string $name,
        string $type,
        string $bodyFormat,
        ?string $body,
        ?string $subject = null,
        ?string $description = null,
        bool $isActive = true,
        array $options = [],
    ): Template;

    public function update(
        string $id,
        ?string $name = null,
        ?string $bodyFormat = null,
        ?string $body = null,
        ?string $subject = null,
        ?string $description = null,
        ?bool $isActive = null,
        ?array $options = null,
    ): Template;

    public function delete(string $id): void;
}
