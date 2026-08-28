<?php

namespace App\Application\UseCases\AgentTool;

use App\Domain\AgentTools\AgentGrant;
use App\Domain\AgentTools\AgentGrantStoreInterface;

/**
 * Mints the one-turn credential and describes it the way the payload carries it.
 *
 * Called from ProcessOpenAIRequest once the catalogue for this agent is
 * resolved (phase 2). Kept as its own use case so the credential's shape has one
 * author, and so it can be tested without dispatching a chat message.
 *
 * The endpoint travels WITH the grant rather than being configured on the
 * worker, so this installation's internal topology stays a server-side
 * decision.
 */
class IssueAgentGrantUseCase
{
    public function __construct(private AgentGrantStoreInterface $grants) {}

    /**
     * @return array{token: string, endpoint: string, expires_at: string}
     */
    public function execute(AgentGrant $grant): array
    {
        $ttl = (int) config('agent_tools.grant_ttl');

        return [
            'token' => $this->grants->issue($grant, $ttl),
            'endpoint' => (string) config('agent_tools.endpoint'),
            'expires_at' => now()->addSeconds($ttl)->toIso8601String(),
        ];
    }

    /** Called when the turn's reply lands, so the normal lifetime is seconds. */
    public function revoke(string $token): void
    {
        $this->grants->revoke($token);
    }
}
