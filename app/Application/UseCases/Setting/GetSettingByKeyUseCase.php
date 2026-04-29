<?php

namespace App\Application\UseCases\Setting;

use App\Domain\Repositories\SettingRepositoryInterface;

class GetSettingByKeyUseCase
{
    public function __construct(private SettingRepositoryInterface $settingRepository) {}

    public function execute(string $key): ?array
    {
        $setting = $this->settingRepository->findByKey($key);
        return $setting?->toDto()->toArray();
    }
}
