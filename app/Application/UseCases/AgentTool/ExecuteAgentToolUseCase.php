<?php

namespace App\Application\UseCases\AgentTool;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Domain\AgentTools\AdminToolRegistry;
use App\Domain\AgentTools\AgentGrant;
use App\Domain\AgentTools\AgentGrantStoreInterface;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\ArgumentValidatorInterface;
use App\Domain\AgentTools\Exceptions\AgentToolFailure;
use App\Domain\Exceptions\AuthorizationException;
use App\Domain\Services\TenantConnectionSwitcherInterface;
use App\Models\Admin as AdminModel;
use App\Models\AgentTool as AgentToolModel;
use Illuminate\Support\Facades\Log;

/**
 * The executor for a tenant ADMIN's agent.
 *
 * Its tools are curated: which handler is exposed, how it is described and how
 * much it may return are landlord rows an operator edits. Authorization is the
 * RBAC slug the tool declares, checked against the acting admin — the agent
 * never exceeds the human it acts for.
 */
class ExecuteAgentToolUseCase extends AbstractExecuteAgentToolUseCase
{
    public function __construct(
        AgentGrantStoreInterface $grants,
        ArgumentValidatorInterface $validator,
        TenantConnectionSwitcherInterface $tenantConnection,
        LogAuditUseCase $logAudit,
        private AdminToolRegistry $registry,
        private AuthorizeActionUseCase $authorize,
    ) {
        parent::__construct($grants, $validator, $tenantConnection, $logAudit);
    }

    protected function expectedActorType(): string
    {
        return 'admin';
    }

    protected function resolve(string $name): array
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

    protected function maxRowsFor(?object $row): int
    {
        $configured = (int) config('agent_tools.max_rows');

        return $row === null ? $configured : min((int) $row->max_rows, $configured);
    }

    protected function authorizeActor(AgentGrant $grant, AgentToolInterface $tool): void
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
            // Caught explicitly. Left to bubble it would surface as a 500 — a
            // regression this codebase has already had in three controllers —
            // and a 500 aborts the turn instead of letting the agent explain.
            throw AgentToolFailure::permissionDenied($slug);
        }
    }
}
