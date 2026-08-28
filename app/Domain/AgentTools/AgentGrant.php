<?php

namespace App\Domain\AgentTools;

/**
 * The one-turn credential's claims.
 *
 * Held server-side in Redis and addressed by an opaque token, so the worker
 * carries a random string and nothing else. Three claims are load-bearing and
 * must never be accepted from the request body instead:
 *
 *  - `database`        resolved once at mint time; the tenant connection comes
 *                      from here, so no argument can reach another tenant.
 *  - `tools`           this turn's allowlist. Even a fully compromised worker
 *                      cannot reach a tool the agent was not granted.
 *  - `impersonatedBy`  copied from the originating session, so a support
 *                      session cannot be laundered into a write.
 */
final readonly class AgentGrant
{
    public function __construct(
        public string $tenantId,
        public string $database,
        public string $actorId,
        public string $actorType,
        public string $chatId,
        public ?string $agentProfileId,
        public ?string $openaiRequestId,
        public string $requestId,
        /** @var array<int, string> */
        public array $tools,
        public ?string $impersonatedBy,
        public int $maxCalls,
        public string $issuedAt,
    ) {}

    public function allows(string $tool): bool
    {
        return in_array($tool, $this->tools, true);
    }

    public function isImpersonated(): bool
    {
        return $this->impersonatedBy !== null && $this->impersonatedBy !== '';
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'database' => $this->database,
            'actor_id' => $this->actorId,
            'actor_type' => $this->actorType,
            'chat_id' => $this->chatId,
            'agent_profile_id' => $this->agentProfileId,
            'openai_request_id' => $this->openaiRequestId,
            'request_id' => $this->requestId,
            'tools' => $this->tools,
            'impersonated_by' => $this->impersonatedBy,
            'max_calls' => $this->maxCalls,
            'issued_at' => $this->issuedAt,
        ];
    }

    public static function fromArray(array $claims): self
    {
        return new self(
            tenantId: (string) ($claims['tenant_id'] ?? ''),
            database: (string) ($claims['database'] ?? ''),
            actorId: (string) ($claims['actor_id'] ?? ''),
            actorType: (string) ($claims['actor_type'] ?? 'admin'),
            chatId: (string) ($claims['chat_id'] ?? ''),
            agentProfileId: $claims['agent_profile_id'] ?? null,
            openaiRequestId: $claims['openai_request_id'] ?? null,
            requestId: (string) ($claims['request_id'] ?? ''),
            tools: array_values(array_map('strval', $claims['tools'] ?? [])),
            impersonatedBy: $claims['impersonated_by'] ?? null,
            maxCalls: (int) ($claims['max_calls'] ?? 0),
            issuedAt: (string) ($claims['issued_at'] ?? ''),
        );
    }
}
