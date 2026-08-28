<?php

namespace App\Application\UseCases\AgentTool;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Domain\AgentTools\AgentGrant;
use App\Domain\AgentTools\AgentGrantStoreInterface;
use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\AgentToolRegistry;
use App\Domain\AgentTools\AgentToolResult;
use App\Domain\AgentTools\ArgumentValidatorInterface;
use App\Domain\AgentTools\Exceptions\AgentToolFailure;
use App\Domain\Exceptions\AuthorizationException;
use App\Domain\Services\TenantConnectionSwitcherInterface;
use App\Models\Admin as AdminModel;
use App\Models\AgentTool as AgentToolModel;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The executor. **The order of the checks below is the security model** — each
 * runs before the next, and none may be skipped for performance.
 *
 * Numbering follows docs/11 §8. One deviation, deliberate: the tenant
 * connection (step 6) is opened right after the grant is read rather than
 * between steps 5 and 7, so that a REFUSED call still lands in the tenant's own
 * audit log. The order of the checks themselves is unchanged, and the
 * connection target still comes only from the grant, so nothing about the
 * threat model moves. The catalogue lookup is unaffected because AgentTool
 * pins its own `landlord` connection.
 *
 * Only `grant_invalid` escapes auditing, and unavoidably: without a valid grant
 * there is no tenant to audit into. That one is logged instead.
 */
class ExecuteAgentToolUseCase
{
    public function __construct(
        private AgentGrantStoreInterface $grants,
        private AgentToolRegistry $registry,
        private ArgumentValidatorInterface $validator,
        private TenantConnectionSwitcherInterface $tenantConnection,
        private AuthorizeActionUseCase $authorize,
        private LogAuditUseCase $logAudit,
    ) {}

    public function execute(string $token, string $name, array $arguments): array
    {
        // 2. Grant. Missing and expired are one outcome on purpose.
        $grant = $this->grants->find($token);

        if (! $grant) {
            Log::warning('Agent tool call presented an invalid grant', ['tool' => $name]);

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

            // 4. This turn's allowlist. Checked BEFORE the catalogue, so a
            // disabled-but-granted tool and a never-granted one look identical
            // from outside.
            if (! $grant->allows($name)) {
                throw AgentToolFailure::toolNotAllowed();
            }

            // 5. Catalogue row + registry. The row selects among REGISTERED
            // handlers; it never instantiates the class it names.
            [$row, $tool] = $this->resolve($name);

            // 7. A support session may read, but never write. The framework
            // will not do this for us: ImpersonationGuard returns early when
            // there is no $request->user(), and this route has none.
            if ($grant->isImpersonated() && $tool->isMutating()) {
                throw AgentToolFailure::readOnlySession();
            }

            // 8. Arguments are untrusted input — the model wrote them, partly
            // from text earlier tools returned.
            $validated = $this->validator->validate($arguments, $this->schemaFor($row, $tool));

            // 9. The agent never exceeds the human.
            $this->authorizeActor($grant, $tool);

            // 10 & 11. Execute, then cap.
            $result = $this->run($tool, $validated, $grant, $row);
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
            // 12. Refusals are audited too: "the agent tried to read users and
            // was denied" is exactly the line a customer will ask about.
            $this->audit($grant, null, $name, $arguments, $failure->errorCode);

            throw $failure;
        } finally {
            Log::info('Agent tool call', [
                'tool' => $name,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 1),
            ]);
        }
    }

    /**
     * @return array{0: AgentToolModel, 1: AgentToolInterface}
     */
    private function resolve(string $name): array
    {
        $row = AgentToolModel::where('name', $name)->where('is_active', true)->first();

        if (! $row) {
            throw AgentToolFailure::toolNotFound();
        }

        $tool = $this->registry->forHandler($row->handler);

        if (! $tool) {
            // A row naming a class nobody registered is a misconfiguration, not
            // an attack — but it must never become an instantiation.
            Log::warning('Agent tool catalogue names an unregistered handler', [
                'tool' => $name,
                'handler' => $row->handler,
            ]);

            throw AgentToolFailure::toolNotFound();
        }

        return [$row, $tool];
    }

    /**
     * The catalogue may narrow a handler's schema; the handler's own schema is
     * the outer bound, so an override that is not an object is ignored rather
     * than trusted.
     */
    private function schemaFor(AgentToolModel $row, AgentToolInterface $tool): array
    {
        $override = $row->parameters_override;

        return is_array($override) && $override !== [] ? $override : $tool->parameters();
    }

    private function authorizeActor(AgentGrant $grant, AgentToolInterface $tool): void
    {
        $slug = $tool->permission();

        if ($slug === null) {
            return;
        }

        $admin = AdminModel::find($grant->actorId);

        if (! $admin || ! $admin->is_active) {
            throw AgentToolFailure::permissionDenied($slug);
        }

        try {
            $this->authorize->execute(AdminFactory::createFromModel($admin), $slug);
        } catch (AuthorizationException) {
            // Caught explicitly. Left to bubble it would surface as a 500 —
            // a regression this codebase has already had in three controllers —
            // and a 500 aborts the turn instead of letting the agent explain.
            throw AgentToolFailure::permissionDenied($slug);
        }
    }

    private function run(
        AgentToolInterface $tool,
        array $arguments,
        AgentGrant $grant,
        AgentToolModel $row,
    ): AgentToolResult {
        $context = new AgentToolContext(
            tenantId: $grant->tenantId,
            actorId: $grant->actorId,
            actorType: $grant->actorType,
            chatId: $grant->chatId,
            requestId: $grant->requestId,
            impersonatedBy: $grant->impersonatedBy,
            maxRows: min($row->max_rows, (int) config('agent_tools.max_rows')),
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

    private function audit(
        AgentGrant $grant,
        ?AgentToolModel $row,
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
                modelType: AgentToolModel::class,
                modelId: $row?->id,
                description: "Agent tool {$name}: {$outcome}",
                tags: ['ai', 'agent-tool'],
                metadata: [
                    'tool' => $name,
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
            // Auditing must never be the reason a call fails. Losing the row is
            // bad; turning every tool call into a 500 because the audit table
            // is unhappy is worse.
            Log::error('Failed to audit an agent tool call', [
                'tool' => $name,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
