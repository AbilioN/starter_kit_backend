<?php

namespace App\Application\UseCases\System;

use App\Application\UseCases\Backup\CheckBackupStalenessUseCase;
use App\Console\Commands\CheckTenantDatabasesCommand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

/**
 * Readiness: can this instance actually serve traffic, and is the work it
 * hands off still being picked up?
 *
 * Deliberately NOT the liveness probe. Liveness answers "is the process
 * alive" and must not touch a single dependency — a liveness check that
 * queries MySQL turns a 30-second database blip into a container restart
 * loop, which is strictly worse than the blip. See HealthController.
 *
 * Two rules hold every check below together:
 *
 *  - **No check may throw.** A readiness probe that 500s tells the operator
 *    nothing about *which* dependency broke, which is the only thing it
 *    exists to say.
 *  - **No check may iterate tenants.** This runs at probe frequency; opening
 *    one connection per tenant database each time is a self-inflicted
 *    outage as the tenant count grows. Per-tenant database health belongs on
 *    a schedule (Sprint 5.2), reporting its last result here.
 */
class CheckSystemHealthUseCase
{
    private const OK = 'ok';

    private const DEGRADED = 'degraded';

    private const DOWN = 'down';

    private const SKIPPED = 'skipped';

    /**
     * Dependencies whose failure means this instance cannot serve requests at
     * all. Everything else degrades the system without taking it down: the
     * API still answers, some work is delayed.
     */
    private const CRITICAL = ['database', 'redis'];

    /**
     * @return array{status: string, checks: array<string, array<string, mixed>>}
     */
    public function execute(): array
    {
        $checks = [
            'database' => $this->checkLandlordDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'horizon' => $this->checkHorizon(),
            'tenant_databases' => $this->checkTenantDatabases(),
            'ai_bus' => $this->checkAiBus(),
            'failed_jobs' => $this->checkFailedJobs(),
            'backups' => $this->checkBackups(),
            'configuration' => $this->checkConfiguration(),
        ];

        return [
            'status' => $this->aggregate($checks),
            'checks' => $checks,
        ];
    }

    /**
     * Landlord, not tenant: it is the connection every request needs before a
     * tenant is even resolved, and it is the same for all of them.
     */
    private function checkLandlordDatabase(): array
    {
        return $this->timed(function () {
            DB::connection('landlord')->select('select 1');

            return ['status' => self::OK];
        });
    }

    private function checkRedis(): array
    {
        return $this->timed(function () {
            $pong = Redis::connection()->ping();

            // phpredis returns true, predis returns '+PONG' — neither is
            // worth asserting on beyond "it answered".
            return $pong ? ['status' => self::OK] : ['status' => self::DOWN, 'error' => 'no response to PING'];
        });
    }

    /**
     * Only the local disk is probed. Reaching an S3-compatible endpoint is a
     * network round trip on every probe, and a probe that is slow or flaky
     * under load is worse than no probe — that belongs on the scheduled check
     * (Sprint 5.2), not here.
     */
    private function checkStorage(): array
    {
        return $this->timed(function () {
            $disk = config('filesystems.default');

            if (! in_array($disk, ['local', 'public'], true)) {
                return [
                    'status' => self::SKIPPED,
                    'disk' => $disk,
                    'reason' => 'remote disk not probed per request',
                ];
            }

            $root = config("filesystems.disks.{$disk}.root");

            return is_writable($root)
                ? ['status' => self::OK, 'disk' => $disk]
                : ['status' => self::DEGRADED, 'disk' => $disk, 'error' => 'root not writable'];
        });
    }

    /**
     * Horizon's master supervisor is what actually runs the queue workers —
     * the chat, notifications and the whole AI pipeline are queued jobs, so
     * "API up, Horizon down" is a system that accepts messages and never
     * delivers them. That state used to be entirely invisible.
     */
    private function checkHorizon(): array
    {
        return $this->timed(function () {
            $masters = app(MasterSupervisorRepository::class)->all();

            if ($masters === []) {
                return ['status' => self::DEGRADED, 'error' => 'no master supervisor running'];
            }

            $statuses = array_map(fn ($master) => $master->status ?? 'unknown', $masters);

            return [
                'status' => in_array('paused', $statuses, true) ? self::DEGRADED : self::OK,
                'masters' => count($masters),
                'statuses' => array_values(array_unique($statuses)),
            ];
        });
    }

    /**
     * Reports the scheduled tenant-database check's last result (see
     * CheckTenantDatabasesCommand) rather than doing the work here.
     *
     * The age of that result is part of the answer: a check that stopped
     * running looks identical to a healthy system if you only read its verdict,
     * which is how a dead cron goes unnoticed for weeks.
     */
    private function checkTenantDatabases(): array
    {
        return $this->timed(function () {
            $last = Cache::get(CheckTenantDatabasesCommand::CACHE_KEY);

            if (! is_array($last)) {
                return [
                    'status' => self::SKIPPED,
                    'reason' => 'scheduled check has not run yet',
                ];
            }

            $age = $this->ageInSeconds($last['checked_at']);
            $maxAge = (int) config('health.tenant_databases.max_age_minutes') * 60;
            $failed = $last['failed'] ?? [];

            $problems = [];

            if ($failed !== []) {
                $problems[] = count($failed).' unreachable: '.implode(', ', array_column($failed, 'subdomain'));
            }

            if ($age > $maxAge) {
                $problems[] = "last checked {$age}s ago";
            }

            return array_filter([
                'status' => $problems === [] ? self::OK : self::DEGRADED,
                'total' => $last['total'] ?? null,
                'unreachable' => count($failed),
                'checked_seconds_ago' => $age,
                'problems' => $problems ?: null,
            ], fn ($value) => $value !== null);
        });
    }

    /**
     * Tenants whose last successful backup is older than their own plan allows.
     *
     * Reads the landlord ledger and the plans, never a tenant connection — so
     * it still answers for a tenant whose database is the broken thing, which
     * is the case that matters most here.
     *
     * Memoised for five minutes rather than computed per probe: the underlying
     * question changes at most once per backup run, and a readiness probe polled
     * every few seconds must not turn into a few hundred landlord queries a
     * second as the tenant count grows.
     */
    /**
     * Settings this process cannot work without, read from the environment this
     * process actually has.
     *
     * That last part is the whole point, and it is why this is a health check
     * rather than a startup assertion or a test. Every check above asks about a
     * shared dependency, so it answers the same in any container. This one
     * answers differently in each, which is what makes it able to catch the
     * failure it was written for: on 2026-08-28 the scheduler was found running
     * current code against the .env baked into the image a week earlier —
     * APP_KEY empty, BACKUP_ENCRYPTION_KEY empty, no ALERT_* at all. Every
     * scheduled backup had been dying on that for a week, and the process meant
     * to report it was the same one that could not send.
     *
     * Degraded, never down: the API serves fine in this state. It is the
     * background work that quietly does not happen.
     */
    private function checkConfiguration(): array
    {
        return $this->timed(function () {
            $problems = [];

            // Empty here means every encrypted:array cast fails in this
            // process — infrastructure_providers.config above all, which holds
            // each tenant's Pusher, S3 and BYOK credentials.
            if (blank(config('app.key'))) {
                $problems[] = 'APP_KEY is not set';
            }

            if (config('backup.encryption.enabled') && blank(config('backup.encryption.key'))) {
                $problems[] = 'BACKUP_ENCRYPTION_KEY is not set while backup encryption is enabled';
            }

            // A process that can detect problems and not report them is worse
            // than one that cannot detect them, because it looks covered.
            if (! config('alerting.mail.enabled') && ! config('alerting.slack.enabled')) {
                $problems[] = 'no alert channel is enabled — failures will be detected and never reported';
            }

            return array_filter([
                'status' => $problems === [] ? self::OK : self::DEGRADED,
                'problems' => $problems === [] ? null : $problems,
            ], fn ($value) => $value !== null);
        });
    }

    private function checkBackups(): array
    {
        return $this->timed(function () {
            $result = Cache::remember(
                'health:backup-staleness',
                now()->addMinutes(5),
                fn () => app(CheckBackupStalenessUseCase::class)->execute(),
            );

            if ($result['checked'] === 0) {
                return ['status' => self::SKIPPED, 'reason' => 'no subject has backups enabled'];
            }

            $stale = $result['stale'];

            return array_filter([
                'status' => $stale === [] ? self::OK : self::DEGRADED,
                'checked' => $result['checked'],
                'stale' => count($stale),
                'never_backed_up' => $result['never'],
                'subjects' => $stale === [] ? null : array_column($stale, 'tenant'),
            ], fn ($value) => $value !== null);
        });
    }

    /**
     * The AI request/response bus. These are raw Redis lists shared with the
     * Python worker, not Laravel queues, so nothing else in the system
     * observes them — not Horizon, not `queue:failed`.
     *
     * Depth alone is ambiguous (a busy worker and a dead one both leave items
     * on the list), so age of the oldest entry and the worker heartbeat are
     * what actually separate "loaded" from "broken".
     */
    private function checkAiBus(): array
    {
        return $this->timed(function () {
            $config = config('health.ai_bus');

            $depth = (int) Redis::llen($config['request_queue']);
            $oldestAge = $this->oldestRequestAgeSeconds($config['request_queue']);
            $heartbeatAge = $this->heartbeatAgeSeconds($config['heartbeat_key']);

            $status = self::OK;
            $problems = [];

            if ($heartbeatAge === null) {
                $status = self::DEGRADED;
                $problems[] = 'no worker heartbeat';
            } elseif ($heartbeatAge > $config['heartbeat_max_age_seconds']) {
                $status = self::DEGRADED;
                $problems[] = "worker heartbeat stale ({$heartbeatAge}s)";
            }

            if ($oldestAge !== null && $oldestAge > $config['max_age_seconds']) {
                $status = self::DEGRADED;
                $problems[] = "oldest request waiting {$oldestAge}s";
            }

            if ($depth > $config['max_depth']) {
                $status = self::DEGRADED;
                $problems[] = "queue depth {$depth}";
            }

            // The one condition every other signal here is blind to. When the
            // provider refuses for billing or a bad key, the bus stays
            // perfectly healthy — queues drain, the worker answers in
            // milliseconds, the heartbeat is fresh — and every answer is an
            // error. Without this the outage is only visible in the worker's
            // stdout, and the person in the chat is told to try again.
            $providerError = $this->providerError($config['provider_error_key'] ?? null);

            if ($providerError !== []) {
                $status = self::DEGRADED;

                foreach ($providerError as $scope => $error) {
                    $problems[] = 'provider refusing requests for '.$scope
                        .' ('.($error['code'] ?? 'unknown').')';
                }
            }

            return array_filter([
                'status' => $status,
                'depth' => $depth,
                'response_depth' => (int) Redis::llen($config['response_queue']),
                'oldest_request_age_seconds' => $oldestAge,
                'worker_heartbeat_age_seconds' => $heartbeatAge,
                'provider_error' => $providerError ?: null,
                'problems' => $problems ?: null,
            ], fn ($value) => $value !== null);
        });
    }

    /**
     * Which tenants the worker currently cannot get an answer for, keyed by
     * tenant id — empty once each has had a call succeed again.
     *
     * The worker removes a tenant's field on ITS next success, so this reports
     * the CURRENT state rather than "it happened once", which is what makes it
     * safe to degrade on and what stops an alert from needing to be silenced
     * by hand after somebody tops the account up.
     *
     * ## Why a hash, and why `detail` never leaves here
     *
     * The credential and the endpoint are per tenant, so a refusal is a fact
     * about one workspace. A single flat key made a healthy tenant's next
     * success erase a broken tenant's outage.
     *
     * `detail` is deliberately dropped. This payload is returned by
     * `GET /api/health/ready`, which is registered with NO authentication, no
     * throttle and no tenant middleware — and the provider's own 401 body
     * quotes the key as a prefix plus its last four characters, while a 429
     * names the organisation. `code` is what an operator acts on; the prose
     * stays in the worker's log and in Redis, where reaching it already means
     * having credentials. The worker redacts it there too.
     *
     * @return array<string, array<string, mixed>>
     */
    private function providerError(?string $key): array
    {
        if ($key === null) {
            return [];
        }

        try {
            $raw = Redis::hgetall($key);
        } catch (\Throwable) {
            // Never let this check be the thing that breaks the health check.
            return [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $scope => $json) {
            $decoded = is_string($json) ? json_decode($json, true) : null;

            if (! is_array($decoded)) {
                continue;
            }

            $out[(string) $scope] = array_filter([
                'code' => $decoded['code'] ?? 'unknown',
                'at' => $decoded['at'] ?? null,
            ], fn ($value) => $value !== null);
        }

        return $out;
    }

    /**
     * The oldest queued request sits at the tail: Laravel LPUSHes and the
     * worker BRPOPs (FIFO), so index -1 is the next one to be handled.
     */
    private function oldestRequestAgeSeconds(string $queue): ?int
    {
        $raw = Redis::lindex($queue, -1);

        if (! $raw) {
            return null;
        }

        $timestamp = json_decode($raw, true)['timestamp'] ?? null;

        if (! is_string($timestamp)) {
            return null;
        }

        try {
            return $this->ageInSeconds($timestamp);
        } catch (Throwable) {
            return null;
        }
    }

    private function heartbeatAgeSeconds(string $key): ?int
    {
        $raw = Redis::get($key);

        if (! $raw) {
            return null;
        }

        $beatAt = json_decode($raw, true)['at'] ?? null;

        if (! is_string($beatAt)) {
            return null;
        }

        try {
            return $this->ageInSeconds($beatAt);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Seconds elapsed since an ISO timestamp, never negative — a worker whose
     * clock is slightly ahead should read as "just now", not as a negative
     * age that then compares as healthy against every threshold.
     */
    private function ageInSeconds(string $isoTimestamp): int
    {
        $seconds = CarbonImmutable::parse($isoTimestamp)->diffInSeconds(CarbonImmutable::now(), false);

        return (int) max(0, round($seconds));
    }

    /**
     * `failed_jobs` is single and on landlord by design (see
     * docs/07-queue-isolation-and-worker-concurrency.md §1). Counting over a
     * window rather than in total: old failures are history, recent ones are
     * an incident in progress.
     */
    private function checkFailedJobs(): array
    {
        return $this->timed(function () {
            $window = (int) config('health.failed_jobs.window_minutes');
            $max = (int) config('health.failed_jobs.max');

            $recent = DB::connection('landlord')
                ->table('failed_jobs')
                ->where('failed_at', '>=', CarbonImmutable::now()->subMinutes($window))
                ->count();

            return [
                'status' => $recent > $max ? self::DEGRADED : self::OK,
                'recent' => $recent,
                'window_minutes' => $window,
            ];
        });
    }

    /**
     * Runs one check, times it, and turns any throwable into a `down` result.
     * The message is included because "which dependency, and what did it say"
     * is the entire value of this endpoint during an incident.
     */
    private function timed(callable $check): array
    {
        $startedAt = microtime(true);

        try {
            $result = $check();
        } catch (Throwable $e) {
            $result = ['status' => self::DOWN, 'error' => $e->getMessage()];
        }

        $result['latency_ms'] = round((microtime(true) - $startedAt) * 1000, 1);

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>>  $checks
     */
    private function aggregate(array $checks): string
    {
        foreach (self::CRITICAL as $name) {
            if (($checks[$name]['status'] ?? null) === self::DOWN) {
                return self::DOWN;
            }
        }

        foreach ($checks as $check) {
            if (in_array($check['status'], [self::DEGRADED, self::DOWN], true)) {
                return self::DEGRADED;
            }
        }

        return self::OK;
    }
}
