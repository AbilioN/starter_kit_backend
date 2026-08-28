<?php

namespace Tests\Support\AgentTools;

use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\AgentToolResult;

/**
 * A handler that exists only for the executor's own tests.
 *
 * Phase 1 proves the ordering guarantees BEFORE any handler can read real
 * customer data — so the security tests run against stubs rather than a real
 * tool, and stay valid when the real catalogue changes.
 */
class StubMutatingTool implements AgentToolInterface
{
    public function name(): string { return 'stub_mutating'; }
    public function description(): string { return 'A stub that claims to mutate.'; }
    public function parameters(): array { return ['type' => 'object', 'properties' => []]; }
    public function permission(): ?string { return null; }
    public function isMutating(): bool { return true; }

    public function execute(array $arguments, AgentToolContext $context): AgentToolResult
    {
        return AgentToolResult::scalar(['changed' => true]);
    }
}
