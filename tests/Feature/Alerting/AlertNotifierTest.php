<?php

namespace Tests\Feature\Alerting;

use App\Domain\Entities\Alert;
use App\Domain\Services\AlertNotifierInterface;
use App\Infrastructure\Services\Alerting\ChannelAlertNotifier;
use App\Infrastructure\Services\Alerting\MailAlertNotifier;
use App\Infrastructure\Services\Alerting\SlackAlertNotifier;
use App\Notifications\SystemAlertNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The delivery half. Every assertion here is really the same one: alerting must
 * not be able to break the thing it watches.
 */
class AlertNotifierTest extends TestCase
{
    private function alert(string $level = Alert::LEVEL_WARNING): Alert
    {
        return new Alert(
            key: 'horizon',
            level: $level,
            title: 'Horizon is degraded',
            message: 'No master supervisor running.',
            context: ['masters' => 0],
        );
    }

    public function test_mail_goes_to_every_configured_recipient(): void
    {
        Notification::fake();
        config(['alerting.mail.to' => 'ops@example.test, oncall@example.test']);

        (new MailAlertNotifier)->send($this->alert());

        Notification::assertSentOnDemand(
            SystemAlertNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === ['ops@example.test', 'oncall@example.test'],
        );
    }

    public function test_mail_with_no_recipient_is_a_no_op_not_an_error(): void
    {
        Notification::fake();
        config(['alerting.mail.to' => null]);

        (new MailAlertNotifier)->send($this->alert());

        Notification::assertNothingSent();
    }

    public function test_slack_posts_the_alert_to_the_webhook(): void
    {
        Http::fake();
        config(['alerting.slack.webhook' => 'https://hooks.slack.test/x', 'alerting.slack.timeout_seconds' => 5]);

        (new SlackAlertNotifier)->send($this->alert(Alert::LEVEL_CRITICAL));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.test/x'
                && str_contains($request['text'], 'CRITICAL')
                && str_contains($request['text'], 'Horizon is degraded');
        });
    }

    public function test_an_unreachable_webhook_does_not_throw(): void
    {
        Http::fake(fn () => throw new \RuntimeException('connection refused'));
        config(['alerting.slack.webhook' => 'https://hooks.slack.test/x']);

        (new SlackAlertNotifier)->send($this->alert());

        $this->assertTrue(true, 'A dead webhook must never propagate out of alerting.');
    }

    /**
     * The reason the two destinations are separate objects: a team on both must
     * not lose the e-mail because the webhook was revoked.
     */
    public function test_one_broken_channel_does_not_cost_the_other(): void
    {
        $working = new class implements AlertNotifierInterface
        {
            public array $alerts = [];

            public function send(Alert $alert): void
            {
                $this->alerts[] = $alert;
            }
        };

        $broken = new class implements AlertNotifierInterface
        {
            public function send(Alert $alert): void
            {
                throw new \RuntimeException('revoked');
            }
        };

        (new ChannelAlertNotifier([$broken, $working]))->send($this->alert());

        $this->assertCount(1, $working->alerts);
    }

    public function test_no_destination_configured_is_survivable(): void
    {
        (new ChannelAlertNotifier([]))->send($this->alert());

        $this->assertTrue(true, 'An unconfigured destination logs and drops; it never throws.');
    }

    public function test_the_subject_says_the_severity_without_opening_the_body(): void
    {
        $this->assertStringStartsWith('[CRITICAL]', $this->alert(Alert::LEVEL_CRITICAL)->subject());
        $this->assertStringStartsWith('[RECOVERED]', $this->alert(Alert::LEVEL_RECOVERED)->subject());
        $this->assertStringStartsWith('[WARNING]', $this->alert()->subject());
    }
}
