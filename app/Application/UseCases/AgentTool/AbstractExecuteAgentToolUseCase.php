<?php

namespace App\Application\UseCases\AgentTool;

use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Domain\AgentTools\AgentGrant;
use App\Domain\AgentTools\AgentGrantStoreInterface;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\AgentToolResult;
use App\Domain\AgentTools\ArgumentValidatorInterface;
use App\Domain\AgentTools\Exceptions\AgentToolFailure;
use App\Domain\Services\TenantConnectionSwitcherInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The part of the executor that is the same whoever is asking.
 *
 * **The order of the checks below is the security model** — each runs before the
 * next, and none may be skipped for performance. Numbering follows docs/11 §8.
 *
 * Subclasses fix three things and nothing else: which registry they read, how a
 * tool name resolves, and how the actor is authorized. Those are *fixed by the
 * subclass*, never chosen at runtime — the endpoint a request arrives at
 * decides which subclass runs it, so there is no conditional anywhere that
 * could let one audience reach the other's tools (docs/15 §4).
 *
 * One deviation from docs/11 §8, deliberate: the tenant connection (step 6) is
 * opened right after the grant is read, so a REFUSED call still lands in the
 * tenant's own audit log. The order of the checks themselves is unchanged, and
 * the connection target still comes only from the grant.
 */
abstract class AbstractExecuteAgentToolUseCase
{
    public function __construct(
        protected AgentGrantStoreInterface $grants,
        protected ArgumentValidatorInterface $validator,
        protected TenantConnectionSwitcherInterface $tenantConnection,
        protected LogAuditUseCase $logAudit,
    ) {}

    /** 'admin' or 'user'. A grant for the other kind is refused outright. */
    abstract protected function expectedActorType(): string;

    /**
     * @return array{0: ?object, 1: AgentToolInterface}  the catalogue row (when
     *         this side has one) and the handler
     */
    abstract protected function resolve(string $name): array;

    /** RBAC for admins; nothing to check for users, whose tools self-scope. */
    abstract protected function authorizeActor(AgentGrant $grant, AgentToolInterface $tool): void;

    /** The row cap for this call. Admin catalogue rows may lower it. */
    abstract protected function maxRowsFor(?object $row): int;

    public function execute(string $token, string $name, array $arguments): array
    {
        // 2. Grant. Missing and expired are one outcome on purpose.
        $grant = $this->grants->find($token);

        if (! $grant) {
            Log::warning('Agent tool call presented an invalid grant', ['tool' => $name]);

            throw AgentToolFailure::grantInvalid();
        }

        // The grant names the endpoint it was minted for, so a mismatch here is
        // an internal routing error rather than anything a person did. Checked
        // before the budget is touched: a routing bug must not spend a real
        // turn's allowance.
        if ($grant->actorType !== $this->expectedActorType()) {
            Log::warning('Agent grant reached the wrong tool endpoint', [
                'tool' => $name,
                'grant_actor_type' => $grant->actorType,
                'endpoint_actor_type' => $this->expectedActorType(),
            ]);

            throw AgentToolFailure::grantInvalid();
        }

        // 6 (hoisted). Target comes from the grant, never from the request.
        return $this->tenantConnection->run(
            $grant->database,
            fn () => $this->runWithinTenant($grant, $token, $name, $arguments),
        );
    }

    private function runWithinTenant(AgentGrant $grant, string $token, string $name, array $arguments): array
    {
        $startedAt = microtime(true);

        try {
            // 3. Budget. A single atomic INCR — see the store.
            $used = $this->grants->consume($token, (int) config('agent_tools.grant_ttl'));

            if ($used > $grant->maxCalls) {
                throw AgentToolFailure::callBudgetExceeded();
            }

            // 4. This turn's allowlist. Checked BEFORE resolution, so a
            // disabled-but-granted tool and a never-granted one look identical
            // from outside.
            if (! $grant->allows($name)) {
                throw AgentToolFailure::toolNotAllowed();
            }

            // 5. Resolution — whatever "resolve" means on this side.
            [$row, $tool] = $this->resolve($name);

            // 7. A support session may read, but never write. The framework will
            // not do this for us: ImpersonationGuard returns early when there is
            // no $request->user(), and this route never has one.
            if ($grant->isImpersonated() && $tool->isMutating()) {
                throw AgentToolFailure::readOnlySession();
            }

            // 8. Arguments are untrusted input — the model wrote them, partly
            // from text earlier tools returned.
            $validated = $this->validator->validate($arguments, $this->schemaFor($row, $tool));

            // 9. Authorization, by whatever means this side has.
            $this->authorizeActor($grant, $tool);

            // 10 & 11. Execute, then cap.
            $result = $this->run($tool, $validated, $grant, $this->maxRowsFor($row));
            $result = $this->capBytes($result, $name);

            $this->audit($grant, $row, $name, $validated, 'ok', $result);

            return [
                'ok' => true,
                'result' => $result->value,
                'row_count' => $result->rowCount,
                'truncated' => $result->truncated,
                'calls_remaining' => max(0, $grant->maxCalls - $used),
            ];
        } catch (AgentToolFailure $failure) {
            // 12. Refusals are audited too: "the agent tried to read X and was
            // denied" is exactly the line a customer will ask about.
            $this->audit($grant, null, $name, $arguments, $failure->errorCode);

            throw $failure;
        } finally {
            Log::info('Agent tool call', [
                'tool' => $name,
                'actor_type' => $grant->actorType,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 1),
            ]);
        }
    }

    /**
     * A catalogue row may narrow a handler's schema; the handler's own schema is
     * the outer bound, so an override that is not an object is ignored rather
     * than trusted. Sides with no catalogue simply pass null.
     */
    protected function schemaFor(?object $row, AgentToolInterface $tool): array
    {
        $override = $row?->parameters_override ?? null;

        return is_array($override) && $override !== [] ? $override : $tool->parameters();
    }

    private function run(
        AgentToolInterface $tool,
        array $arguments,
        AgentGrant $grant,
        int $maxRows,
    ): AgentToolResult {
        $context = new \App\Domain\AgentTools\AgentToolContext(
            tenantId: $grant->tenantId,
            actorId: $grant->actorId,
            actorType: $grant->actorType,
            chatId: $grant->chatId,
            requestId: $grant->requestId,
            impersonatedBy: $grant->impersonatedBy,
            maxRows: $maxRows,
        );

        try {
            return $tool->execute($arguments, $context);
        } catch (AgentToolFailure $failure) {
            throw $failure;
        } catch (Throwable $e) {
            Log::error('Agent tool handler threw', [
                'tool' => $tool->name(),
                'exception' => $e->getMessage(),
            ]);

            throw AgentToolFailure::handlerError();
        }
    }

    /**
     * Backstop for a few very wide rows getting past the row cap. Lists are
     * trimmed; anything else that exceeds the ceiling is a handler returning
     * something oversized, and letting it through would defeat the point of
     * having a ceiling.
     */
    private function capBytes(AgentToolResult $result, string $name): AgentToolResult
    {
        $limit = (int) config('agent_tools.max_result_bytes');
        $encoded = json_encode($result->value);

        if ($encoded === false || strlen($encoded) <= $limit) {
            return $result;
        }

        if (! is_array($result->value) || ! array_is_list($result->value)) {
            Log::error('Agent tool returned an oversized non-list result', [
                'tool' => $name,
                'bytes' => strlen($encoded),
            ]);

            throw AgentToolFailure::handlerError();
        }

        $rows = $result->value;

        while ($rows !== [] && strlen((string) json_encode($rows)) > $limit) {
            array_pop($rows);
        }

        return $result->truncatedTo($rows, count($rows));
    }

    protected function audit(
        AgentGrant $grant,
        ?object $row,
        string $name,
        array $arguments,
        string $outcome,
        ?AgentToolResult $result = null,
    ): void {
        try {
            $this->logAudit->execute(
                userId: $grant->actorId,
                userType: $grant->actorType,
                action: 'agent_tool_invoked',
                modelType: \App\Models\AgentTool::class,
                modelId: $row?->id,
                description: "Agent tool {$name}: {$outcome}",
                tags: ['ai', 'agent-tool'],
                metadata: [
                    'tool' => $name,
                    'actor_type' => $grant->actorType,
                    'arguments' => $arguments,
                    'outcome' => $outcome,
                    'row_count' => $result?->rowCount,
                    'truncated' => $result?->truncated,
                    'request_id' => $grant->requestId,
                    'chat_id' => $grant->chatId,
                    'agent_profile_id' => $grant->agentProfileId,
                    'impersonated_by' => $grant->impersonatedBy,
                ],
            );
        } catch (Throwable $e) {
            // Auditing must never be the reason a call fails.
            Log::error('Failed to audit an agent tool call', [
                'tool' => $name,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
