<?php

namespace App\Infrastructure\CustomFields;

use App\Application\Services\TenantCacheKey;
use App\Application\UseCases\Template\ResolveTemplateLocaleUseCase;
use App\Domain\CustomFields\CompiledCatalogueInterface;
use App\Domain\CustomFields\FieldTypeRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Hands out the right catalogue for the tenant on the current connection.
 *
 * ## The three fingerprints, and why each one is there
 *
 * The class name is
 * `Catalogue_{sha1(database)}_{definitionsVersion}_{generatorFingerprint}`,
 * and the file lives at
 * `storage/app/tenant-fields/{sha1(database)}/{version}_{fingerprint}.php`.
 *
 * - **The database hash** is the tenant discriminator. Redis and the
 *   filesystem are shared by every tenant, and SettingRepository's docblock
 *   is a written account of the cross-tenant cache poisoning that already
 *   happened here once. Keyed on the database NAME rather than `tenants.id`
 *   deliberately: `backup:restore` restores into a NEW database and then
 *   flips `tenants.database_name`, so the name makes a restore a COLD cache
 *   (recompile from the restored rows) where the tenant id would make it a
 *   STALE one — the pre-restore catalogue served against post-restore data.
 *   It is also the only discriminator available inside Horizon and the
 *   scheduler, where `app('currentTenant')` is never bound. Hashed because
 *   the value is a MySQL name in production and an absolute `.sqlite` path
 *   under tests, and one of those is not a class-name component.
 *
 * - **The definitions version** spans all THREE tables. Labels are compiled
 *   into the class, so a version derived from the definitions alone would let
 *   a tenant fix a Portuguese typo and never see it change.
 *
 * - **The generator fingerprint** is in the class NAME and not merely in a
 *   cache key. `horizon`, `openai-listener` and `scheduler` are long-lived
 *   PHP processes that cannot redefine a loaded class.
 *
 * ## Why storage/ and not tmpfs
 *
 * `docker-compose.yml` bind-mounts `./storage` into app, horizon, scheduler
 * and openai-listener. `/tmp` and `/dev/shm` are per-container, so following
 * the study's "materialised on tmpfs" literally would give the four
 * containers four divergent copies — a tenant edit made over HTTP invisible
 * to the worker rendering their export, which is the hardest bug in this
 * feature to reproduce.
 *
 * ## Two caches, and what each one removes
 *
 * The compiled FILE removes the derivation: finding it costs one `is_file()`
 * and loading it costs a `require_once` that opcache serves from memory.
 *
 * The Redis entry removes the QUERIES. Without it the definition rows are
 * read on every single request — three tables, on the hot path of every
 * agenda, list and form — which on a multi-tenant box is the cost that
 * actually matters. Definitions change when an administrator edits them,
 * which is rare, so this is close to the ideal thing to cache.
 *
 * It is keyed through TenantCacheKey because Redis is shared by every tenant
 * under one app-wide prefix. It is busted by every write path (see forget()),
 * and it carries a TTL as the backstop for the one case bustings cannot
 * cover: rows changed out of band, by a seeder, a console command, or someone
 * with a mysql prompt.
 *
 * The version is derived from the CACHED CONTENT rather than from a separate
 * query, so the compiled class and the rows it was built from can never
 * disagree — a stale cache serves a coherent older catalogue rather than a
 * class whose literals contradict its own definitions.
 */
class CatalogueLoader
{
    private const CACHE_KEY = 'custom_fields:definitions';

    /**
     * An hour, matching SettingRepository. Every write busts the key
     * explicitly, so the TTL is not the invalidation mechanism — it is the
     * bound on how long an OUT-OF-BAND change stays invisible: a seeder, a
     * console command, or a row edited by hand during an incident.
     */
    private const CACHE_TTL = 3600;

    /** Per-process memo, keyed by fully-qualified class name. */
    private static array $instances = [];

    /** Whether this process has already reported falling back. */
    private static bool $fallbackLogged = false;

    public function __construct(
        private FieldTypeRegistry $types,
        private FieldCatalogueCompiler $compiler,
        private ResolveTemplateLocaleUseCase $locales,
    ) {}

    public function load(): CompiledCatalogueInterface
    {
        [$definitions, $version] = $this->definitions();
        $fingerprint = $this->compiler->fingerprint($this->types->fingerprintSource());
        $databaseHash = $this->databaseHash();

        $class = "Catalogue_{$databaseHash}_{$version}_{$fingerprint}";
        $fqcn = FieldCatalogueCompiler::NAMESPACE.'\\'.$class;

        if (isset(self::$instances[$fqcn])) {
            return self::$instances[$fqcn];
        }

        try {
            return self::$instances[$fqcn] = $this->materialise($fqcn, $class, $definitions, $version, $databaseHash, $fingerprint);
        } catch (\Throwable $e) {
            // The fallback is real, and it is NOT silent. A compiled catalogue
            // that quietly stops being used turns "the generator broke" into
            // "the app is mysteriously slower", which is the class of
            // degradation this project has already paid a week for.
            if (! self::$fallbackLogged) {
                self::$fallbackLogged = true;
                Log::warning('Custom field catalogue fell back to the reference implementation.', [
                    'class' => $fqcn,
                    'error' => $e->getMessage(),
                ]);
            }

            return new ReferenceFieldCatalogue($definitions, $this->types, $this->locales, $version);
        }
    }

    /** Whether this process has degraded to the interpreter. Read by the health check. */
    public static function hasFallenBack(): bool
    {
        return self::$fallbackLogged;
    }

    /**
     * Called by every path that changes a definition, a label or a role rule.
     *
     * Clears BOTH caches: the per-process memo (so the rest of this request
     * sees the change) and the tenant's cached rows (so the next request, in
     * any of the four containers, rebuilds them). Missing the second is the
     * shape of the invalidation bug ChangeTenantSubscriptionPlanUseCase
     * carried for months — a bust that never matched the key that had been
     * written.
     */
    public static function forget(): void
    {
        self::$instances = [];

        Cache::forget(TenantCacheKey::for(self::CACHE_KEY));
    }

    /**
     * The tenant's rows and their version, from cache when possible.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    private function definitions(): array
    {
        $cached = Cache::get(TenantCacheKey::for(self::CACHE_KEY));

        if (is_array($cached) && array_key_exists('definitions', $cached) && array_key_exists('version', $cached)) {
            return [$cached['definitions'], $cached['version']];
        }

        $definitions = $this->readDefinitions();
        $version = $this->version($definitions);

        Cache::put(
            TenantCacheKey::for(self::CACHE_KEY),
            ['definitions' => $definitions, 'version' => $version],
            self::CACHE_TTL,
        );

        return [$definitions, $version];
    }

    private function materialise(
        string $fqcn,
        string $class,
        array $definitions,
        string $version,
        string $databaseHash,
        string $fingerprint,
    ): CompiledCatalogueInterface {
        if (! class_exists($fqcn, false)) {
            $directory = $this->directory($databaseHash);
            $file = $directory."/{$version}_{$fingerprint}.php";

            if (! is_file($file)) {
                $this->write($directory, $file, $this->compiler->compile($class, $definitions, $version, $this->types));
            }

            require_once $file;
        }

        // No SPL autoloader is registered for this namespace: nothing under
        // storage/ is on a PSR-4 root, and requiring the file here is both
        // simpler and one fewer global hook to reason about.
        return new $fqcn($definitions, $this->types, $this->locales, $version);
    }

    /**
     * Write to a temp file in the SAME directory, then rename.
     *
     * Four containers may compile concurrently, and `rename()` within one
     * filesystem is atomic — so a worker either sees no file or a complete
     * one, and never `require`s something half-written.
     */
    private function write(string $directory, string $file, string $source): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Cannot create catalogue directory [{$directory}].");
        }

        $temp = $directory.'/.'.uniqid('cf', true).'.tmp';

        if (@file_put_contents($temp, $source) === false) {
            throw new \RuntimeException("Cannot write compiled catalogue to [{$temp}].");
        }

        if (! @rename($temp, $file)) {
            @unlink($temp);
            throw new \RuntimeException("Cannot publish compiled catalogue to [{$file}].");
        }
    }

    private function directory(string $databaseHash): string
    {
        return storage_path('app/tenant-fields/'.$databaseHash);
    }

    private function databaseHash(): string
    {
        return substr(sha1((string) DB::connection('tenant')->getDatabaseName()), 0, 12);
    }

    /**
     * A fingerprint of exactly what gets baked into the class.
     *
     * This started out as `count(*)` plus `max(updated_at)` over the three
     * tables, which is the cheap version — and it was wrong within an hour of
     * being written. Creating a field and then reconciling it flips its state
     * from `pending` to `live`, and on a fast machine both writes land in the
     * SAME SECOND. `updated_at` is second-granular and the row count did not
     * change, so the version was identical, the compiled file was reused, and
     * the catalogue kept insisting the tenant had no readable columns while
     * the column was sitting there live. Silent, and exactly the class of
     * staleness the fingerprint exists to prevent.
     *
     * Hashing the normalised rows makes that structurally impossible: if the
     * emitted class would differ, the name differs. It costs nothing extra —
     * readDefinitions() has already run — and it removes three aggregate
     * queries per load.
     *
     * The honest trade-off it exposes: because the rows are read on every
     * load anyway, what the compiled class currently saves is the derivation,
     * not the round trip. Caching the normalised rows themselves (through
     * TenantCacheKey, busted on write) is the optimisation that would change
     * that, and it belongs after a measurement rather than before one — which
     * is what the study's own step 7 says.
     *
     * @param  array<int, array<string, mixed>>  $definitions
     */
    private function version(array $definitions): string
    {
        if ($definitions === []) {
            return $this->tablesExist() ? 'empty' : 'absent';
        }

        return substr(sha1(serialize($definitions)), 0, 12);
    }

    /**
     * Migrations reach existing tenants only through a manual `tenant:migrate`,
     * and "deploy" here is a bind mount plus a container restart — so the
     * window where the code is new and a tenant's schema is not is guaranteed,
     * not hypothetical. An empty catalogue keeps every screen working; a fatal
     * would take the agenda down for every un-migrated tenant.
     */
    private function tablesExist(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('custom_field_definitions');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The normalised rows both implementations agree on.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readDefinitions(): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        $rows = DB::connection('tenant')->table('custom_field_definitions')
            ->orderBy('host')->orderBy('position')->orderBy('num')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('id')->all();

        $labels = DB::connection('tenant')->table('custom_field_labels')
            ->whereIn('definition_id', $ids)->get()->groupBy('definition_id');

        $rules = DB::connection('tenant')->table('custom_field_role_rules')
            ->whereIn('definition_id', $ids)->get()->groupBy('definition_id');

        return $rows->map(function ($row) use ($labels, $rules) {
            $ruleRows = $rules->get($row->id, collect());

            return [
                'num' => (int) $row->num,
                'column' => $row->column_name,
                'host' => $row->host,
                'type' => $row->field_type,
                'is_filterable' => (bool) $row->is_filterable,
                'state' => $row->state,
                'items' => $row->items ? json_decode($row->items, true) : null,
                'pattern' => $row->pattern,
                'icon' => $row->icon,
                'colour' => $row->colour,
                'colour_dark' => $row->colour_dark,
                'size' => (int) $row->font_size,
                'slot' => $row->slot,
                'section' => $row->section,
                'position' => (int) $row->position,
                'is_required' => (bool) $row->is_required,
                'labels' => $labels->get($row->id, collect())->mapWithKeys(fn ($l) => [
                    $l->locale => [
                        'label' => $l->label,
                        'help_text' => $l->help_text,
                        'placeholder' => $l->placeholder,
                    ],
                ])->all(),
                'hidden_role_ids' => $ruleRows->where('rule', 'hidden')->pluck('role_id')->values()->all(),
                'readonly_role_ids' => $ruleRows->where('rule', 'readonly')->pluck('role_id')->values()->all(),
                'required_role_ids' => $ruleRows->where('rule', 'required')->pluck('role_id')->values()->all(),
            ];
        })->values()->all();
    }
}
