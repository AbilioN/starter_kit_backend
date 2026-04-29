<?php

namespace App\Helpers;

use App\Domain\Repositories\SettingRepositoryInterface;
use Illuminate\Support\Facades\App;

class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = App::make(SettingRepositoryInterface::class)->findByKey($key);
        return $setting?->value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        App::make(SettingRepositoryInterface::class)->update($key, $value);
    }

    public static function isEnabled(string $featureKey): bool
    {
        return (bool) static::get($featureKey, false);
    }

    public static function all(): array
    {
        return App::make(SettingRepositoryInterface::class)->all();
    }

    public static function publicSettings(): array
    {
        return App::make(SettingRepositoryInterface::class)->allPublic();
    }
}
