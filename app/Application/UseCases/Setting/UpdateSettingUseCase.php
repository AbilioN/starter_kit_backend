<?php

namespace App\Application\UseCases\Setting;

use App\Domain\Exceptions\SettingNotFoundException;
use App\Domain\Repositories\SettingRepositoryInterface;

class UpdateSettingUseCase
{
    public function __construct(private SettingRepositoryInterface $settingRepository) {}

    public function execute(string $key, mixed $value): array
    {
        $existing = $this->settingRepository->findByKey($key);

        if (!$existing) {
            throw new SettingNotFoundException("Setting '{$key}' not found.");
        }

        $updated = $this->settingRepository->update($key, $value);
        return $updated->toDto()->toArray();
    }

    public function executeMany(array $keyValuePairs): void
    {
        foreach (array_keys($keyValuePairs) as $key) {
            $existing = $this->settingRepository->findByKey($key);
            if (!$existing) {
                throw new SettingNotFoundException("Setting '{$key}' not found.");
            }
        }

        $this->settingRepository->updateMany($keyValuePairs);
    }
}
