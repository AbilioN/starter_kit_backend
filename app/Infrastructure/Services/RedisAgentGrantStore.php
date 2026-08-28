<?php

namespace App\Infrastructure\Services;

use App\Domain\AgentTools\AgentGrant;
use App\Domain\AgentTools\AgentGrantStoreInterface;
use Illuminate\Support\Facades\Redis;

/**
 * Grants as opaque tokens with the claims held server-side.
 *
 * Chosen over a signed claim set (see docs/11 §3): revocation is a DEL, the use
 * counter is the same object, there is no signature surface because the worker
 * only ever holds a random string, and nothing has to be distributed or
 * rotated. A signed token would still need server-side state for the counter,
 * so statelessness buys no round trip here.
 *
 * These keys are Laravel's alone — the worker never reads them, it echoes the
 * token back. Unlike the raw `openai_requests` lists shared with the Python
 * worker, nothing here depends on REDIS_PREFIX staying empty.
 */
class RedisAgentGrantStore implements AgentGrantStoreInterface
{
    private const PREFIX = 'agent_grant:';

    public function issue(AgentGrant $grant, int $ttlSeconds): string
    {
        // 32 bytes from the CSPRNG. Not derived from anything about the turn:
        // a token that encodes its own context is a token that leaks it.
        $token = bin2hex(random_bytes(32));

        Redis::setex(self::PREFIX.$token, $ttlSeconds, json_encode($grant->toArray()));

        return $token;
    }

    public function find(string $token): ?AgentGrant
    {
        if (! $this->looksLikeToken($token)) {
            return null;
        }

        $raw = Redis::get(self::PREFIX.$token);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $claims = json_decode($raw, true);

        return is_array($claims) ? AgentGrant::fromArray($claims) : null;
    }

    public function consume(string $token, int $ttlSeconds): int
    {
        $key = self::PREFIX.$token.':calls';

        // One atomic INCR, then a TTL so an abandoned counter cannot outlive
        // the day. Read-then-write here would lose an update whenever a model
        // round emits two tool calls at once — which is the normal case, not an
        // edge one.
        $used = (int) Redis::incr($key);
        Redis::expire($key, $ttlSeconds);

        return $used;
    }

    public function revoke(string $token): void
    {
        if (! $this->looksLikeToken($token)) {
            return;
        }

        Redis::del(self::PREFIX.$token, self::PREFIX.$token.':calls');
    }

    /**
     * Cheap shape check before the token reaches a Redis key name. Keeps
     * anything with a colon, a newline or unbounded length out of the keyspace.
     */
    private function looksLikeToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }
}
