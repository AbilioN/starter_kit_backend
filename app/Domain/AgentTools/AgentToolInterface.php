<?php

namespace App\Domain\AgentTools;

/**
 * One capability the AI agent may call.
 *
 * A handler declares what it does and what it needs; it does NOT enforce any of
 * it. Tenant connection, permission check, argument validation, row capping and
 * auditing are the executor's job (ExecuteAgentToolUseCase), precisely so a
 * handler author cannot forget one.
 *
 * Handlers wrap existing use cases wherever one fits, rather than querying
 * directly. One caveat that has already caught us: **a use case that reads
 * `Auth::` or the session cannot be wrapped as-is.** There is no authenticated
 * user on this path — the actor arrives in the grant — and the failure is
 * silent, not loud (see docs/12 §3).
 */
interface AgentToolInterface
{
    /** Function name the model sees. Must match ^[a-z][a-z0-9_]{0,63}$ */
    public function name(): string;

    /** Default description. The catalogue row may override it. */
    public function description(): string;

    /** JSON Schema for the arguments object. Also what is validated server-side. */
    public function parameters(): array;

    /**
     * RBAC slug the ACTOR must hold. Null only for a tool that touches no
     * tenant data at all.
     */
    public function permission(): ?string;

    /** Authoritative. The `is_mutating` column exists only for display. */
    public function isMutating(): bool;

    public function execute(array $arguments, AgentToolContext $context): AgentToolResult;
}
