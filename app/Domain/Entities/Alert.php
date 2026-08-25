<?php

namespace App\Domain\Entities;

/**
 * One thing worth waking someone up about — or telling them is over.
 *
 * `key` is what makes an alert de-duplicable: it identifies the *condition*
 * (the check that failed), not the occurrence. Two alerts with the same key are
 * the same ongoing problem, which is the whole basis of not sending the same
 * message every five minutes for a week.
 */
class Alert
{
    public const LEVEL_WARNING = 'warning';

    public const LEVEL_CRITICAL = 'critical';

    public const LEVEL_RECOVERED = 'recovered';

    public function __construct(
        public readonly string $key,
        public readonly string $level,
        public readonly string $title,
        public readonly string $message,
        public readonly array $context = [],
    ) {}

    public function isRecovery(): bool
    {
        return $this->level === self::LEVEL_RECOVERED;
    }

    /**
     * Prefixed so an alert is recognisable in a subject line or a channel
     * without reading the body — which is how most of them will be read.
     */
    public function subject(): string
    {
        $prefix = match ($this->level) {
            self::LEVEL_CRITICAL => '[CRITICAL]',
            self::LEVEL_RECOVERED => '[RECOVERED]',
            default => '[WARNING]',
        };

        return trim($prefix.' '.config('app.name').' — '.$this->title);
    }
}
