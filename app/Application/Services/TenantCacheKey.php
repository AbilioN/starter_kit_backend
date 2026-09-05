<?php

namespace App\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * The one place a cache key gets its tenant dimension.
 *
 * Redis is shared by every tenant and the cache prefix from config/cache.php
 * is app-wide, not tenant-aware: without a discriminator the first tenant to
 * read `features.ai_agent` poisons that key for every other tenant for the
 * full TTL. That bug already happened here; SettingRepository::cacheKey() was
 * written to stop it.
 *
 * This class exists because that fix was made in one file and the code that
 * INVALIDATES those keys was written in another. ChangeTenantSubscriptionPlanUseCase
 * forgot `setting:limits.max_storage_mb` while the repository had written
 * `starter_kit_tenant_a:setting:limits.max_storage_mb` — so the bust never
 * matched anything, and a comment above it claimed the opposite. Two files
 * computing the same key independently is how invalidation stops working
 * without anything failing loudly. Every read, write and forget goes through
 * here now.
 *
 * The discriminator is the live database name rather than the tenant id
 * because it is the only one available everywhere: `app('currentTenant')` is
 * bound solely by IdentifyTenant (HTTP), and Horizon, the scheduler and
 * openai-listener never see it. It is also the correct key after
 * `backup:restore`, which restores into a NEW database and flips
 * tenants.database_name — a restore is then a cold cache rather than a stale
 * one serving pre-restore answers against post-restore data.
 */
final class TenantCacheKey
{
    /**
     * Prefix a suffix with the database the current connection points at.
     *
     * Under the test suite this is an absolute .sqlite path rather than a
     * MySQL name. That is fine for a cache key — it still discriminates —
     * but it is why callers must never reuse the result as a file path or a
     * class-name component without hashing it first.
     */
    public static function for(string $suffix): string
    {
        return (DB::connection()->getDatabaseName() ?? 'default').':'.$suffix;
    }
}
