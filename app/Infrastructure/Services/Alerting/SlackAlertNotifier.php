<?php

namespace App\Infrastructure\Services\Alerting;

use App\Domain\Entities\Alert;
use App\Domain\Services\AlertNotifierInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Slack incoming webhook. Plain HTTP rather than the Slack notification
 * channel package — one POST with a JSON body needs no dependency, and this
 * runs in the path that must not add failure modes.
 */
class SlackAlertNotifier implements AlertNotifierInterface
{
    public function send(Alert $alert): void
    {
        $webhook = config('alerting.slack.webhook');

        if (blank($webhook)) {
            Log::warning('alerting.slack.no_webhook', ['alert' => $alert->key]);

            return;
        }

        $emoji = match ($alert->level) {
            Alert::LEVEL_CRITICAL => ':rotating_light:',
            Alert::LEVEL_RECOVERED => ':white_check_mark:',
            default => ':warning:',
        };

        $lines = [$emoji.' *'.$alert->subject().'*', $alert->message];

        foreach ($alert->context as $key => $value) {
            $lines[] = '• `'.$key.'`: '.(is_array($value) ? implode(', ', $value) : var_export($value, true));
        }

        try {
            $response = Http::timeout((int) config('alerting.slack.timeout_seconds'))
                ->post($webhook, ['text' => implode("\n", $lines)]);

            if ($response->failed()) {
                Log::error('alerting.slack.rejected', [
                    'alert' => $alert->key,
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            // Swallowed on purpose: an unreachable webhook must never turn a
            // degraded queue into a failed health check.
            Log::error('alerting.slack.failed', ['alert' => $alert->key, 'error' => $e->getMessage()]);
        }
    }
}
