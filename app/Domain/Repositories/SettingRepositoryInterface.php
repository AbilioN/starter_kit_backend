<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Setting;

interface SettingRepositoryInterface
{
    public function all(): array;
    public function allPublic(): array;
    public function byGroup(string $group): array;
    public function findByKey(string $key): ?Setting;
    public function update(string $key, mixed $value): Setting;
    public function updateMany(array $keyValuePairs): void;
}
