<?php

namespace App\Infrastructure\Services\Alerting;

use App\Domain\Entities\Alert;
use App\Domain\Services\AlertNotifierInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fans one alert out to every enabled destination, independently.
 *
 * "Independently" is the point: a team on both e-mail and Slack must not lose
 * the e-mail because the webhook was revoked. Each destination already
 * swallows its own failures; this catches anything they miss.
 *
 * With nothing enabled, the alert is logged and dropped — visible to anyone
 * reading logs, and unable to break the caller.
 */
class ChannelAlertNotifier implements AlertNotifierInterface
{
    /**
     * @param  array<int, AlertNotifierInterface>  $channels
     */
    public function __construct(private array $channels) {}

    public function send(Alert $alert): void
    {
        if ($this->channels === []) {
            Log::warning('alerting.no_destination', [
                'alert' => $alert->key,
                'level' => $alert->level,
                'title' => $alert->title,
                'message' => $alert->message,
            ]);

            return;
        }

        foreach ($this->channels as $channel) {
            try {
                $channel->send($alert);
            } catch (Throwable $e) {
                Log::error('alerting.channel.failed', [
                    'alert' => $alert->key,
                    'channel' => $channel::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
