<?php

namespace Tests\Feature\System;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RequestContextTest extends TestCase
{
    public function test_every_response_carries_a_request_id(): void
    {
        $header = $this->getJson('/api/health')->headers->get('X-Request-Id');

        $this->assertNotEmpty($header);
    }

    public function test_the_request_id_reaches_the_log_context(): void
    {
        $this->getJson('/api/health');

        $this->assertArrayHasKey('request_id', Log::sharedContext());
    }

    public function test_two_requests_get_different_ids(): void
    {
        $first = $this->getJson('/api/health')->headers->get('X-Request-Id');
        $second = $this->getJson('/api/health')->headers->get('X-Request-Id');

        $this->assertNotSame($first, $second);
    }

    public function test_a_caller_supplied_request_id_is_reused(): void
    {
        // So a caller's own trace and ours line up on the same identifier.
        $response = $this->withHeader('X-Request-Id', 'nuxt-abc-123')->getJson('/api/health');

        $this->assertSame('nuxt-abc-123', $response->headers->get('X-Request-Id'));
    }

    public function test_a_malformed_request_id_is_replaced(): void
    {
        // The value is written into log lines, so an unvalidated header would
        // let a caller inject newlines — and forge log entries with them.
        $response = $this->withHeader('X-Request-Id', "bad\nvalue with spaces")->getJson('/api/health');

        $this->assertNotSame("bad\nvalue with spaces", $response->headers->get('X-Request-Id'));
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_an_overlong_request_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Request-Id', str_repeat('a', 200))->getJson('/api/health');

        $this->assertLessThanOrEqual(64, strlen($response->headers->get('X-Request-Id')));
    }
}
