<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\InfrastructureProvider;
use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use App\Models\InfrastructureProvider as InfrastructureProviderModel;

class InfrastructureProviderRepository implements InfrastructureProviderRepositoryInterface
{
    public function findById(string $id): ?InfrastructureProvider
    {
        return InfrastructureProviderModel::find($id)?->toEntity();
    }

    public function findAll(): array
    {
        return InfrastructureProviderModel::orderBy('type')->orderBy('name')->get()
            ->map(fn (InfrastructureProviderModel $provider) => $provider->toEntity())
            ->all();
    }

    public function findByType(string $type): array
    {
        return InfrastructureProviderModel::where('type', $type)->orderBy('name')->get()
            ->map(fn (InfrastructureProviderModel $provider) => $provider->toEntity())
            ->all();
    }

    public function create(
        string $type,
        string $name,
        array $config,
        bool $isActive = true,
    ): InfrastructureProvider {
        $provider = InfrastructureProviderModel::create([
            'type' => $type,
            'name' => $name,
            'config' => $config,
            'is_active' => $isActive,
        ]);

        return $provider->toEntity();
    }

    // $type is deliberately not updatable here — same reasoning as
    // Template::type being immutable after creation: config's shape is
    // type-specific, so changing type would leave it stale/wrong-shaped.
    public function update(
        string $id,
        ?string $name = null,
        ?array $config = null,
        ?bool $isActive = null,
    ): InfrastructureProvider {
        $provider = InfrastructureProviderModel::findOrFail($id);

        $updateData = array_filter([
            'name' => $name,
            'config' => $config,
            'is_active' => $isActive,
        ], fn ($value) => $value !== null);

        $provider->update($updateData);

        return $provider->fresh()->toEntity();
    }

    public function delete(string $id): void
    {
        InfrastructureProviderModel::findOrFail($id)->delete();
    }
}
