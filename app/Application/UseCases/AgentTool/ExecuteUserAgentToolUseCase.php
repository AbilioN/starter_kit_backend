<?php

namespace App\Application\UseCases\AgentTool;

use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Domain\AgentTools\AgentGrant;
use App\Domain\AgentTools\AgentGrantStoreInterface;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\ArgumentValidatorInterface;
use App\Domain\AgentTools\Exceptions\AgentToolFailure;
use App\Domain\AgentTools\UserToolRegistry;
use App\Domain\Services\TenantConnectionSwitcherInterface;
use App\Models\User as UserModel;

/**
 * The executor for a tenant's END USER's agent.
 *
 * Two things make this different from the admin executor, and both are
 * deliberate:
 *
 * **There is no catalogue.** The user's tool set is static, fixed in code and
 * identical for every user of every tenant. Curation exists so the party paying
 * can shape what it pays for, and the end user is not the customer — the tenant
 * is (docs/15 §3).
 *
 * **There is no permission check, because there is nothing to check against.**
 * Only Admin implements AuthorizableUser; permissions describe who may
 * administer the tenant, and end users administer nothing. What replaces the
 * check is the shape of the tools themselves: a user tool takes identity only
 * from the grant and validates every identifier the model passes against the
 * actor before using it. The guarantee is that the tool is *incapable* of
 * returning someone else's data, which is stronger than a check that could be
 * mis-declared — and it is verified per tool by test, not at runtime.
 */
class ExecuteUserAgentToolUseCase extends AbstractExecuteAgentToolUseCase
{
    public function __construct(
        AgentGrantStoreInterface $grants,
        ArgumentValidatorInterface $validator,
        TenantConnectionSwitcherInterface $tenantConnection,
        LogAuditUseCase $logAudit,
        private UserToolRegistry $registry,
    ) {
        parent::__construct($grants, $validator, $tenantConnection, $logAudit);
    }

    protected function expectedActorType(): string
    {
        return 'user';
    }

    protected function resolve(string $name): array
    {
        $tool = $this->registry->forName($name);

        // `tool_not_found`, not `permission_denied`: an admin tool asked for
        // here genuinely does not exist on this side. Answering "denied" would
        // imply something was refused that could otherwise have run, and would
        // tell an outsider what the other endpoint holds.
        if (! $tool) {
            throw AgentToolFailure::toolNotFound();
        }

        return [null, $tool];
    }

    protected function maxRowsFor(?object $row): int
    {
        return (int) config('agent_tools.max_rows');
    }

    /**
     * Nothing to authorize — but the actor must still exist and be usable. A
     * grant whose user has since been deleted or deactivated must stop working
     * immediately, without waiting for its TTL.
     */
    protected function authorizeActor(AgentGrant $grant, AgentToolInterface $tool): void
    {
        $user = UserModel::find($grant->actorId);

        if (! $user) {
            throw AgentToolFailure::permissionDenied(null);
        }
    }
}
