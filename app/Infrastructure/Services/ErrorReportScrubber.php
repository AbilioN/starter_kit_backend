<?php

namespace App\Infrastructure\Services;

use Sentry\Event;

/**
 * Removes this installation's own secrets from an error report before it leaves
 * the building (roadmap 5.1.B).
 *
 * `send_default_pii=false` already keeps out cookies, IPs and user identities.
 * It does nothing about the other half, which is the half this product has more
 * of: a `PDOException` quotes the DSN it failed to connect with, a Guzzle
 * exception quotes the URL it called, and under BYOK that URL can carry a
 * tenant's own provider key. None of that is PII and all of it is worse.
 *
 * **Exact values, never patterns.** Every string redacted here is one this
 * process can read out of its own configuration, so a match is a certainty
 * rather than a guess. Regex-hunting for "things that look like keys" produces
 * both misses and false positives, and a false positive here means blanking the
 * one line of a stack trace that explained the bug.
 *
 * The tenant database name is on the list for the same reason the token
 * contract in 4.10 keeps it out of claims: how tenants are stored is not
 * something an external service needs to be told, and the name is a map of the
 * estate.
 */
class ErrorReportScrubber
{
    private const REDACTED = '[redacted]';

    /**
     * Below this length a "secret" is more likely to be a substring of ordinary
     * prose than the secret itself — blanking every occurrence of a 4-character
     * password would corrupt the report without protecting anything.
     */
    private const MINIMUM_LENGTH = 8;

    public function scrub(Event $event): Event
    {
        $secrets = $this->secrets();

        if ($secrets === []) {
            return $event;
        }

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue($this->redact($exception->getValue(), $secrets));
        }

        if ($event->getMessage() !== null) {
            $event->setMessage($this->redact($event->getMessage(), $secrets));
        }

        $event->setExtra($this->redactDeep($event->getExtra(), $secrets));

        return $event;
    }

    /**
     * @return array<int, string>
     */
    private function secrets(): array
    {
        $candidates = [
            config('app.key'),
            config('database.connections.mysql.password'),
            config('database.connections.landlord.password'),
            config('database.connections.tenant.password'),
            // The tenant currently being served, not every tenant: this runs on
            // the way out of a request that already belongs to exactly one.
            config('database.connections.tenant.database'),
            config('broadcasting.connections.pusher.secret'),
            config('backup.encryption.key'),
            config('services.openai.api_key'),
        ];

        return array_values(array_unique(array_filter(
            array_map(fn ($value) => is_string($value) ? $value : null, $candidates),
            fn (?string $value) => $value !== null && strlen($value) >= self::MINIMUM_LENGTH,
        )));
    }

    /**
     * @param  array<int, string>  $secrets
     */
    private function redact(?string $subject, array $secrets): string
    {
        return str_replace($secrets, self::REDACTED, (string) $subject);
    }

    /**
     * @param  array<mixed>  $values
     * @param  array<int, string>  $secrets
     * @return array<mixed>
     */
    private function redactDeep(array $values, array $secrets): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = match (true) {
                is_string($value) => $this->redact($value, $secrets),
                is_array($value) => $this->redactDeep($value, $secrets),
                default => $value,
            };
        }

        return $values;
    }
}
