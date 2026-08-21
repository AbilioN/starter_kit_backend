<?php

namespace Tests\Feature\System;

use Illuminate\Support\Facades\Redis;
use Tests\TenantTestCase;

class HealthCheckTest extends TenantTestCase
{
    private string $heartbeatKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->heartbeatKey = config('health.ai_bus.heartbeat_key');
        Redis::del($this->heartbeatKey);
    }

    protected function tearDown(): void
    {
        Redis::del($this->heartbeatKey);

        parent::tearDown();
    }

    public function test_liveness_answers_without_a_tenant(): void
    {
        // No ?tenant= and no subdomain: every other /api route would 404 here,
        // which is exactly the failure mode this route must not have — a probe
        // never carries tenant context.
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_liveness_does_not_report_on_dependencies(): void
    {
        // If liveness ever grows a dependency check, an orchestrator will
        // start restarting containers over a transient database blip. Asserting
        // on the absence of `checks` is what makes that regression loud.
        $this->getJson('/api/health')->assertJsonMissingPath('checks');
    }

    public function test_readiness_reports_on_every_dependency(): void
    {
        $response = $this->getJson('/api/health/ready');

        foreach (['database', 'redis', 'storage', 'horizon', 'ai_bus', 'failed_jobs'] as $check) {
            $response->assertJsonStructure(['checks' => [$check => ['status', 'latency_ms']]]);
        }

        $this->assertContains($response->json('status'), ['ok', 'degraded', 'down']);
    }

    public function test_readiness_confirms_the_landlord_database_and_redis(): void
    {
        $this->getJson('/api/health/ready')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.redis.status', 'ok');
    }

    public function test_a_missing_ai_worker_heartbeat_degrades_the_bus(): void
    {
        // The case worth catching: a dead worker with an empty queue looks
        // exactly like a healthy idle one from every other angle.
        $response = $this->getJson('/api/health/ready');

        $response->assertJsonPath('checks.ai_bus.status', 'degraded');
        $this->assertContains('no worker heartbeat', $response->json('checks.ai_bus.problems'));
    }

    public function test_a_fresh_heartbeat_keeps_the_bus_healthy(): void
    {
        Redis::setex($this->heartbeatKey, 60, json_encode([
            'at' => now()->toISOString(),
            'pid' => 1,
            'concurrency' => 4,
        ]));

        $this->getJson('/api/health/ready')
            ->assertJsonPath('checks.ai_bus.status', 'ok')
            ->assertJsonPath('checks.ai_bus.depth', 0);
    }

    public function test_a_stale_heartbeat_degrades_the_bus(): void
    {
        $maxAge = (int) config('health.ai_bus.heartbeat_max_age_seconds');

        Redis::setex($this->heartbeatKey, 60, json_encode([
            'at' => now()->subSeconds($maxAge + 30)->toISOString(),
        ]));

        $response = $this->getJson('/api/health/ready');

        $response->assertJsonPath('checks.ai_bus.status', 'degraded');
        $this->assertStringContainsString(
            'heartbeat stale',
            implode(' ', $response->json('checks.ai_bus.problems')),
        );
    }

    public function test_a_backed_up_request_queue_degrades_the_bus(): void
    {
        Redis::setex($this->heartbeatKey, 60, json_encode(['at' => now()->toISOString()]));

        $queue = config('health.ai_bus.request_queue');
        $maxAge = (int) config('health.ai_bus.max_age_seconds');

        // The oldest request sits at the tail: Laravel LPUSHes, the worker
        // BRPOPs. Pushing one stale entry is enough to prove the age is read
        // from the right end of the list.
        Redis::lpush($queue, json_encode([
            'id' => 'openai_test',
            'timestamp' => now()->subSeconds($maxAge + 60)->toISOString(),
        ]));

        try {
            $response = $this->getJson('/api/health/ready');

            $response->assertJsonPath('checks.ai_bus.status', 'degraded');
            $this->assertGreaterThan($maxAge, $response->json('checks.ai_bus.oldest_request_age_seconds'));
        } finally {
            Redis::del($queue);
        }
    }

    public function test_readiness_stays_200_while_merely_degraded(): void
    {
        // Pulling an instance out of rotation because the AI queue is slow
        // would turn a delay into an outage.
        $this->getJson('/api/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'degraded');
    }
}
