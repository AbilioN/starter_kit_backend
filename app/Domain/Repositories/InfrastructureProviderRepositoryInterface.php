<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\InfrastructureProvider;

interface InfrastructureProviderRepositoryInterface
{
    public function findById(string $id): ?InfrastructureProvider;

    public function findAll(): array;

    public function findByType(string $type): array;

    public function create(
        string $type,
        string $name,
        array $config,
        bool $isActive = true,
    ): InfrastructureProvider;

    public function update(
        string $id,
        ?string $name = null,
        ?array $config = null,
        ?bool $isActive = null,
    ): InfrastructureProvider;

    public function delete(string $id): void;
}
