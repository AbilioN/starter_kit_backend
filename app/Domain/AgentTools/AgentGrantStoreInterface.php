<?php

namespace App\Domain\AgentTools;

interface AgentGrantStoreInterface
{
    /** Mints a grant and returns its opaque token. */
    public function issue(AgentGrant $grant, int $ttlSeconds): string;

    public function find(string $token): ?AgentGrant;

    /**
     * Atomically records one use and returns the running total.
     *
     * Must be a single INCR, never read-then-write: two tool calls from one
     * model round arrive concurrently, and a lost update there is a budget that
     * does not bound anything.
     */
    public function consume(string $token, int $ttlSeconds): int;

    /** Called when the turn's reply lands, so the normal lifetime is short. */
    public function revoke(string $token): void;
}
