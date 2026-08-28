<?php

namespace App\Domain\AgentTools;

/**
 * Everything a handler is allowed to know about the turn it is running in.
 *
 * All of it comes from the grant, never from the request body — a model can be
 * argued into passing any tenant id, so no handler ever receives one it could
 * have chosen.
 */
final readonly class AgentToolContext
{
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $actorType,
        public string $chatId,
        public string $requestId,
        public ?string $impersonatedBy,
        public int $maxRows,
    ) {}
}
