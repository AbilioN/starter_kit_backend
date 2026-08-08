<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Template;

interface TemplateRepositoryInterface
{
    public function findById(string $id): ?Template;

    // Well-known system-slot lookup (e.g. 'welcome_email') — see the `key`
    // column's docblock in its migration for why this is the only way a
    // template gets tagged with one, never through create()/update().
    public function findByKey(string $key): ?Template;

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
        ?string $key = null,
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
