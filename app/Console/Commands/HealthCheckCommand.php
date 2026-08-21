<?php

namespace App\Console\Commands;

use App\Application\UseCases\System\CheckSystemHealthUseCase;
use Illuminate\Console\Command;

/**
 * The same readiness checks as GET /api/health/ready, from the CLI.
 *
 * Two reasons this exists as well as the endpoint: it is what a Docker
 * HEALTHCHECK or a CI smoke step can call without an HTTP client, and it is
 * the single place to attach alerting once a destination is chosen
 * (Sprint 5.1.E — Slack and email are both still open). Keeping that one
 * place explicit is what stops alerting logic from being scattered across
 * whatever code happens to notice a problem first.
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'health:check {--json : Output the raw payload}';

    protected $description = 'Run the readiness checks (database, redis, storage, horizon, AI bus, failed jobs)';

    public function handle(CheckSystemHealthUseCase $checkSystemHealth): int
    {
        $result = $checkSystemHealth->execute();

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

    private function detail(array $check): string
    {
        $detail = collect($check)
            ->except(['status', 'latency_ms'])
            ->map(fn ($value, $key) => $key.'='.(is_array($value) ? implode('|', $value) : var_export($value, true)))
            ->implode(' ');

        return mb_strimwidth($detail, 0, 80, '…');
    }
}
