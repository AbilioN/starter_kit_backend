<?php

namespace Tests\Feature\Alerting;

use App\Application\UseCases\System\DispatchHealthAlertsUseCase;
use App\Domain\Entities\Alert;
use App\Domain\Services\AlertNotifierInterface;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The detection half was already tested (HealthCheckTest). What is worth
 * asserting here is the restraint: alerting that fires on every blip and
 * repeats every five minutes is worth the same as no alerting, and takes
 * longer to build.
 */
class HealthAlertingTest extends TestCase
{
    private RecordingNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->notifier = new RecordingNotifier;
        $this->app->instance(AlertNotifierInterface::class, $this->notifier);

        config([
            'alerting.min_occurrences' => 2,
            'alerting.repeat_after_minutes' => 180,
            'alerting.notify_recovery' => true,
            'alerting.critical_checks' => ['database', 'redis'],
        ]);
    }

    private function dispatch(array $checks): array
    {
        return app(DispatchHealthAlertsUseCase::class)->execute([
            'status' => 'degraded',
            'checks' => $checks,
        ]);
    }

    private function degraded(string $name = 'horizon'): array
    {
        return [$name => ['status' => 'degraded', 'latency_ms' => 4.2, 'error' => 'no master supervisor running']];
    }

    public function test_a_single_failing_run_does_not_alert(): void
    {
        $summary = $this->dispatch($this->degraded());

        $this->assertSame(0, $summary['sent']);
        $this->assertSame(1, $summary['suppressed']);
        $this->assertSame([], $this->notifier->alerts);
    }

    public function test_it_alerts_once_the_problem_persists(): void
    {
        $this->dispatch($this->degraded());
        $summary = $this->dispatch($this->degraded());

        $this->assertSame(1, $summary['sent']);
        $this->assertCount(1, $this->notifier->alerts);
        $this->assertSame('horizon', $this->notifier->alerts[0]->key);
        $this->assertSame(Alert::LEVEL_WARNING, $this->notifier->alerts[0]->level);
    }

    /**
     * The rule that keeps the channel readable: an ongoing incident is
     * announced once, not every five minutes for a week.
     */
    public function test_an_ongoing_problem_is_not_repeated_immediately(): void
    {
        foreach (range(1, 6) as $ignored) {
            $this->dispatch($this->degraded());
        }

        $this->assertCount(1, $this->notifier->alerts);
    }

    public function test_an_unresolved_problem_is_repeated_after_the_interval(): void
    {
        $this->dispatch($this->degraded());
        $this->dispatch($this->degraded());

        $this->travel(4)->hours();
        $this->dispatch($this->degraded());

        $this->assertCount(2, $this->notifier->alerts);
    }

    public function test_recovery_is_announced(): void
    {
        $this->dispatch($this->degraded());
        $this->dispatch($this->degraded());

        $summary = $this->dispatch(['horizon' => ['status' => 'ok']]);

        $this->assertSame(1, $summary['recovered']);
        $this->assertTrue($this->notifier->alerts[1]->isRecovery());
    }

    /**
     * A problem that never crossed the threshold was never news, so its ending
     * is not news either — otherwise every blip produces an all-clear for an
     * incident nobody was told about.
     */
    public function test_recovery_from_an_unannounced_blip_is_silent(): void
    {
        $this->dispatch($this->degraded());

        $summary = $this->dispatch(['horizon' => ['status' => 'ok']]);

        $this->assertSame(0, $summary['recovered']);
        $this->assertSame([], $this->notifier->alerts);
    }

    public function test_down_is_critical_and_degraded_on_a_core_dependency_escalates(): void
    {
        $this->dispatch(['redis' => ['status' => 'degraded'], 'ai_bus' => ['status' => 'down']]);
        $this->dispatch(['redis' => ['status' => 'degraded'], 'ai_bus' => ['status' => 'down']]);

        $levels = collect($this->notifier->alerts)->pluck('level', 'key')->all();

        $this->assertSame(Alert::LEVEL_CRITICAL, $levels['redis']);
        $this->assertSame(Alert::LEVEL_CRITICAL, $levels['ai_bus']);
    }

    public function test_a_skipped_check_is_not_a_problem(): void
    {
        $this->dispatch(['storage' => ['status' => 'skipped', 'reason' => 'remote disk not probed']]);
        $summary = $this->dispatch(['storage' => ['status' => 'skipped', 'reason' => 'remote disk not probed']]);

        $this->assertSame(0, $summary['sent']);
        $this->assertSame([], $this->notifier->alerts);
    }

    /**
     * Alerting hangs off the health check. A destination that explodes must
     * never make the health check itself look broken.
     */
    public function test_a_throwing_notifier_does_not_break_the_dispatch(): void
    {
        $this->app->instance(AlertNotifierInterface::class, new class implements AlertNotifierInterface
        {
            public function send(Alert $alert): void
            {
                throw new \RuntimeException('webhook revoked');
            }
        });

        $this->dispatch($this->degraded());
        $summary = $this->dispatch($this->degraded());

        $this->assertSame(0, $summary['sent']);
    }

    /**
     * Latency changes every run; including it would make two reports of the
     * same incident look like different ones.
     */
    public function test_latency_is_left_out_of_the_alert_context(): void
    {
        $this->dispatch($this->degraded());
        $this->dispatch($this->degraded());

        $this->assertArrayNotHasKey('latency_ms', $this->notifier->alerts[0]->context);
        $this->assertArrayHasKey('error', $this->notifier->alerts[0]->context);
    }
}

class RecordingNotifier implements AlertNotifierInterface
{
    /** @var array<int, Alert> */
    public array $alerts = [];

    public function send(Alert $alert): void
    {
        $this->alerts[] = $alert;
    }
}
