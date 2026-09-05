<?php

namespace App\Infrastructure\Repositories;

use App\Application\Services\TenantCacheKey;
use App\Domain\Entities\Setting;
use App\Domain\Repositories\SettingRepositoryInterface;
use App\Models\Setting as SettingModel;
use Illuminate\Support\Facades\Cache;

class SettingRepository implements SettingRepositoryInterface
{
    private const CACHE_TTL = 3600;
    private const CACHE_PREFIX = 'setting:';
    private const CACHE_ALL = 'settings:all';
    private const CACHE_PUBLIC = 'settings:public';

    public function all(): array
    {
        return Cache::remember($this->cacheKey(self::CACHE_ALL), self::CACHE_TTL, function () {
            return SettingModel::orderBy('group')->orderBy('key')
                ->get()
                ->map(fn($m) => $m->toEntity())
                ->all();
        });
    }

    public function allPublic(): array
    {
        return Cache::remember($this->cacheKey(self::CACHE_PUBLIC), self::CACHE_TTL, function () {
            return SettingModel::where('is_public', true)
                ->orderBy('group')->orderBy('key')
                ->get()
                ->map(fn($m) => $m->toEntity())
                ->all();
        });
    }

    public function byGroup(string $group): array
    {
        return SettingModel::where('group', $group)
            ->orderBy('key')
            ->get()
            ->map(fn($m) => $m->toEntity())
            ->all();
    }

    public function findByKey(string $key): ?Setting
    {
        return Cache::remember($this->cacheKey(self::CACHE_PREFIX . $key), self::CACHE_TTL, function () use ($key) {
            $model = SettingModel::where('key', $key)->first();
            return $model?->toEntity();
        });
    }

    /**
     * Settings live in the tenant DB, but the Redis cache key prefix
     * (config/cache.php) is app-wide, not tenant-aware — without this,
     * the first tenant to read a key (e.g. features.ai_agent) poisons the
     * cache for every other tenant for up to CACHE_TTL.
     *
     * The rule now lives in TenantCacheKey rather than here, because
     * ChangeTenantSubscriptionPlanUseCase writes to the Setting model
     * directly and has to forget the same keys — and when it computed them
     * itself it got them wrong, silently, for as long as the code existed.
     */
    private function cacheKey(string $suffix): string
    {
        return TenantCacheKey::for($suffix);
    }

    public function update(string $key, mixed $value): Setting
    {
        $model = SettingModel::where('key', $key)->firstOrFail();

        $raw = match ($model->type) {
            'boolean' => $value ? 'true' : 'false',
            'array', 'json' => json_encode($value),
            default => (string) $value,
        };

        $model->update(['value' => $raw]);

        $this->bustCache($key);

        return $model->fresh()->toEntity();
    }

    public function updateMany(array $keyValuePairs): void
    {
        foreach ($keyValuePairs as $key => $value) {
            $this->update($key, $value);
        }
    }

    private function bustCache(string $key): void
    {
        Cache::forget($this->cacheKey(self::CACHE_PREFIX . $key));
        Cache::forget($this->cacheKey(self::CACHE_ALL));
        Cache::forget($this->cacheKey(self::CACHE_PUBLIC));
    }
}
