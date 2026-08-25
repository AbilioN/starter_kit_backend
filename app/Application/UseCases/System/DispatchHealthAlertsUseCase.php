<?php

namespace App\Application\UseCases\System;

use App\Domain\Entities\Alert;
use App\Domain\Services\AlertNotifierInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a readiness result into alerts — and, far more importantly, decides
 * when NOT to send one.
 *
 * Detecting a problem was the easy half and already existed
 * (CheckSystemHealthUseCase). The half that decides whether an alerting system
 * is still read in two weeks is this one:
 *
 *  - **A single failing run is not an incident.** One slow Redis round trip, one
 *    container mid-restart. `min_occurrences` consecutive failures are required
 *    before the first message, because paging on blips is how people learn to
 *    ignore the channel.
 *  - **An ongoing problem is announced once**, then repeated only after
 *    `repeat_after_minutes`. The reminder exists for a first alert that was
 *    missed, not to keep score.
 *  - **Recovery is announced too.** Without it, the only signal that an incident
 *    ended is that the alerts stopped — which is indistinguishable from the
 *    alerting having died, and that ambiguity is exactly what this sprint set
 *    out to remove.
 *
 * State lives in the cache and is keyed per check, because each
 * `health:check --alert` is a fresh CLI process that remembers nothing.
 */
class DispatchHealthAlertsUseCase
{
    private const PROBLEM_STATUSES = ['degraded', 'down'];

    public function __construct(
        private AlertNotifierInterface $notifier,
    ) {}

    /**
     * @param  array{status: string, checks: array<string, array<string, mixed>>}  $health
     * @return array{sent: int, suppressed: int, recovered: int}
     */
    public function execute(array $health): array
    {
        $sent = 0;
        $suppressed = 0;
        $recovered = 0;

        foreach ($health['checks'] as $name => $check) {
            $status = $check['status'] ?? 'ok';

            try {
                if (in_array($status, self::PROBLEM_STATUSES, true)) {
                    $this->handleProblem($name, $status, $check) ? $sent++ : $suppressed++;

                    continue;
                }

                if ($this->handleRecovery($name)) {
                    $recovered++;
                }
            } catch (Throwable $e) {
                // Per check, so one unserialisable payload cannot cost the
                // alerts for every other check in the same run.
                Log::error('alerting.dispatch.failed', ['check' => $name, 'error' => $e->getMessage()]);
            }
        }

        return ['sent' => $sent, 'suppressed' => $suppressed, 'recovered' => $recovered];
    }

    /**
     * @return bool whether an alert was actually sent
     */
    private function handleProblem(string $name, string $status, array $check): bool
    {
        $state = $this->state($name) ?? ['occurrences' => 0, 'first_seen' => now()->toIso8601String(), 'last_notified' => null];
        $state['occurrences']++;
        $state['status'] = $status;

        $shouldSend = $this->shouldSend($state);

        if ($shouldSend) {
            $state['last_notified'] = now()->toIso8601String();
        }

        $this->store($name, $state);

        if (! $shouldSend) {
            return false;
        }

        $this->notifier->send(new Alert(
            key: $name,
            level: $this->level($name, $status),
            title: $this->title($name, $status),
            message: $this->message($name, $status, $state),
            context: $this->context($check),
        ));

        return true;
    }

    private function shouldSend(array $state): bool
    {
        if ($state['occurrences'] < (int) config('alerting.min_occurrences')) {
            return false;
        }

        if ($state['last_notified'] === null) {
            return true;
        }

        return CarbonImmutable::parse($state['last_notified'])
            ->addMinutes((int) config('alerting.repeat_after_minutes'))
            ->isPast();
    }

    private function handleRecovery(string $name): bool
    {
        $state = $this->state($name);

        Cache::forget($this->key($name));

        // Only announce recovery from something that was actually announced.
        // A problem that never crossed min_occurrences was never news, so its
        // ending is not news either.
        if ($state === null || $state['last_notified'] === null || ! config('alerting.notify_recovery')) {
            return false;
        }

        $since = CarbonImmutable::parse($state['first_seen']);

        $this->notifier->send(new Alert(
            key: $name,
            level: Alert::LEVEL_RECOVERED,
            title: ucfirst(str_replace('_', ' ', $name)).' is healthy again',
            message: sprintf(
                'The `%s` check is back to ok after %s.',
                $name,
                $since->diffForHumans(now(), true),
            ),
            context: ['first_seen' => $state['first_seen']],
        ));

        return true;
    }

    /**
     * `down` is always critical. `degraded` escalates to critical only on the
     * dependencies without which the instance cannot serve at all — the same
     * list CheckSystemHealthUseCase treats as critical.
     */
    private function level(string $name, string $status): string
    {
        if ($status === 'down') {
            return Alert::LEVEL_CRITICAL;
        }

        return in_array($name, (array) config('alerting.critical_checks'), true)
            ? Alert::LEVEL_CRITICAL
            : Alert::LEVEL_WARNING;
    }

    private function title(string $name, string $status): string
    {
        return ucfirst(str_replace('_', ' ', $name)).' is '.$status;
    }

    private function message(string $name, string $status, array $state): string
    {
        return sprintf(
            'The `%s` readiness check has reported %s for %d consecutive run(s), since %s.',
            $name,
            $status,
            $state['occurrences'],
            $state['first_seen'],
        );
    }

    /**
     * Everything the check reported except its own bookkeeping. Latency is
     * dropped: it changes every run and would make two reports of the same
     * incident look like different ones.
     */
    private function context(array $check): array
    {
        return collect($check)->except(['status', 'latency_ms'])->all();
    }

    private function state(string $name): ?array
    {
        $state = Cache::get($this->key($name));

        return is_array($state) ? $state : null;
    }

    private function store(string $name, array $state): void
    {
        Cache::put($this->key($name), $state, now()->addHours((int) config('alerting.state_ttl_hours')));
    }

    private function key(string $name): string
    {
        return config('alerting.state_prefix').$name;
    }
}
