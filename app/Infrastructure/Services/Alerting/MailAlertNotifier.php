<?php

namespace App\Infrastructure\Services\Alerting;

use App\Domain\Entities\Alert;
use App\Domain\Services\AlertNotifierInterface;
use App\Notifications\SystemAlertNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * On-demand routed, not stored against a user: these go to whoever operates the
 * platform, who is not necessarily an account in it.
 */
class MailAlertNotifier implements AlertNotifierInterface
{
    public function send(Alert $alert): void
    {
        $recipients = $this->recipients();

        if ($recipients === []) {
            // Logged rather than thrown. A missing ALERT_MAIL_TO is a
            // configuration gap; turning it into an exception would make the
            // health check fail because nobody set up e-mail, which is a worse
            // outcome than a quiet gap.
            Log::warning('alerting.mail.no_recipients', ['alert' => $alert->key]);

            return;
        }

        try {
            Notification::route('mail', $recipients)->notify(new SystemAlertNotification($alert));
        } catch (Throwable $e) {
            Log::error('alerting.mail.failed', ['alert' => $alert->key, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function recipients(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('alerting.mail.to')),
        )));
    }
}
