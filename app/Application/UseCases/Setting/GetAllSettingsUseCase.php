<?php

namespace App\Application\UseCases\Setting;

use App\Domain\Repositories\SettingRepositoryInterface;

class GetAllSettingsUseCase
{
    public function __construct(private SettingRepositoryInterface $settingRepository) {}

    public function execute(bool $publicOnly = false, ?string $group = null): array
    {
        if ($publicOnly) {
            $settings = $this->settingRepository->allPublic();
        } elseif ($group !== null) {
            $settings = $this->settingRepository->byGroup($group);
        } else {
            $settings = $this->settingRepository->all();
        }

        return array_map(fn($s) => $s->toDto()->toArray(), $settings);
    }
}
