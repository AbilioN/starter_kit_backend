<?php

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Services\ErrorReportScrubber;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Tests\TestCase;

/**
 * Roadmap 5.1.B. `send_default_pii=false` keeps out cookies, IPs and
 * identities; it does nothing about the secrets this application quotes in its
 * own exception messages, which is the half a BtoB product has more of.
 */
class ErrorReportScrubberTest extends TestCase
{
    private function eventWithException(string $message): Event
    {
        $event = Event::createEvent();
        $event->setExceptions([new ExceptionDataBag(new \RuntimeException($message))]);

        return $event;
    }

    public function test_it_redacts_a_database_password_quoted_in_a_connection_error(): void
    {
        config(['database.connections.mysql.password' => 'super-secret-password']);

        $event = (new ErrorReportScrubber)->scrub($this->eventWithException(
            "SQLSTATE[HY000] [1045] Access denied for user 'app'@'%' (using password: super-secret-password)"
        ));

        $value = $event->getExceptions()[0]->getValue();

        $this->assertStringNotContainsString('super-secret-password', $value);
        $this->assertStringContainsString('[redacted]', $value);
        // The rest of the message survives — a scrubber that blanks the line
        // explaining the bug has cost more than it saved.
        $this->assertStringContainsString('Access denied for user', $value);
    }

    /**
     * Under database-per-tenant the database name is a map of the estate, and
     * an external service has no business being told how tenants are stored.
     */
    public function test_it_redacts_the_tenant_database_name(): void
    {
        config(['database.connections.tenant.database' => 'tenant_acme_prod']);

        $event = (new ErrorReportScrubber)->scrub(
            $this->eventWithException("Table 'tenant_acme_prod.chats' doesn't exist")
        );

        $this->assertStringNotContainsString('tenant_acme_prod', $event->getExceptions()[0]->getValue());
    }

    public function test_it_redacts_secrets_nested_in_extra_context(): void
    {
        config(['broadcasting.connections.pusher.secret' => 'pusher-secret-value']);

        $event = $this->eventWithException('boom');
        $event->setExtra(['payload' => ['auth' => 'pusher-secret-value', 'chat_id' => 42]]);

        $extras = (new ErrorReportScrubber)->scrub($event)->getExtra();

        $this->assertSame('[redacted]', $extras['payload']['auth']);
        $this->assertSame(42, $extras['payload']['chat_id']);
    }

    /**
     * A four-character password would otherwise blank every accidental
     * occurrence of those characters in the report.
     */
    public function test_it_ignores_values_too_short_to_be_matched_safely(): void
    {
        config(['database.connections.mysql.password' => 'abc']);

        $event = (new ErrorReportScrubber)->scrub($this->eventWithException('abcdefg happened'));

        $this->assertStringContainsString('abcdefg happened', $event->getExceptions()[0]->getValue());
    }
}
