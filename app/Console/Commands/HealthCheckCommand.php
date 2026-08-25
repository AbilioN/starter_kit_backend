<?php

namespace App\Console\Commands;

use App\Application\UseCases\System\CheckSystemHealthUseCase;
use App\Application\UseCases\System\DispatchHealthAlertsUseCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The same readiness checks as GET /api/health/ready, from the CLI.
 *
 * Two reasons this exists as well as the endpoint: it is what a Docker
 * HEALTHCHECK or a CI smoke step can call without an HTTP client, and it is
 * the single place alerting hangs off (Sprint 5.1.E, shipped 2026-08-21).
 * Keeping that one place explicit is what stops alerting logic from being
 * scattered across whatever code happens to notice a problem first.
 *
 * Alerting is opt-in via --alert, and only the scheduled run passes it. A
 * human running `health:check` to see what is wrong must not, by looking,
 * send everyone else a message.
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'health:check
        {--json : Output the raw payload}
        {--alert : Send alerts for problems (used by the scheduled run)}';

    protected $description = 'Run the readiness checks (database, redis, storage, horizon, AI bus, failed jobs)';

    public function handle(
        CheckSystemHealthUseCase $checkSystemHealth,
        DispatchHealthAlertsUseCase $dispatchAlerts,
    ): int {
        $result = $checkSystemHealth->execute();

        if ($this->option('alert')) {
            $this->alert_($result, $dispatchAlerts);
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Check', 'Status', 'Latency (ms)', 'Detail'],
                collect($result['checks'])->map(fn (array $check, string $name) => [
                    $name,
                    $check['status'],
                    $check['latency_ms'] ?? '',
                    $this->detail($check),
                ])->values()->all(),
            );

            $this->newLine();
            $this->line("Overall: <options=bold>{$result['status']}</>");
        }

        // Distinct exit codes so a caller can treat "slow" differently from
        // "unusable" — a degraded AI queue should page someone, not restart
        // the container.
        return match ($result['status']) {
            'ok' => self::SUCCESS,
            'degraded' => 1,
            default => 2,
        };
    }

    /**
     * Wrapped, and the exit code is never affected by it. A broken alerting
     * destination must not make the health check itself look unhealthy — that
     * is how an operator ends up distrusting the one thing that was working.
     */
    private function alert_(array $result, DispatchHealthAlertsUseCase $dispatchAlerts): void
    {
        try {
            $summary = $dispatchAlerts->execute($result);

            if (! $this->option('json')) {
                $this->line(sprintf(
                    'Alerts: %d sent, %d suppressed, %d recovery notice(s).',
                    $summary['sent'],
                    $summary['suppressed'],
                    $summary['recovered'],
                ));
            }
        } catch (Throwable $e) {
            Log::error('alerting.dispatch.crashed', ['error' => $e->getMessage()]);
        }
    }

    private function detail(array $check): string
    {
        $detail = collect($check)
            ->except(['status', 'latency_ms'])
            ->map(fn ($value, $key) => $key.'='.(is_array($value) ? implode('|', $value) : var_export($value, true)))
            ->implode(' ');

        return mb_strimwidth($detail, 0, 80, '…');
    }
}
