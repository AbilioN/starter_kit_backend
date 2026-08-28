<?php

namespace Tests\Feature\AgentTools;

use App\Domain\AgentTools\AgentGrant;
use App\Domain\AgentTools\AgentGrantStoreInterface;
use App\Domain\AgentTools\AgentToolRegistry;
use App\Models\Admin;
use App\Models\AgentTool;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\Support\AgentTools\StubManyRowsTool;
use Tests\Support\AgentTools\StubMutatingTool;
use Tests\Support\AgentTools\StubPermissionedTool;
use Tests\Support\AgentTools\StubReadTool;
use Tests\Support\AgentTools\StubThrowingTool;
use Tests\TenantTestCase;

/**
 * The executor's ordering guarantees, proven against stubs before any real
 * handler exists (roadmap 4.11, phase 1).
 *
 * Every test here is about a step in docs/11 §8 doing its job *and* doing it in
 * the right order. The order is the security model: an earlier check passing
 * responsibility to a later one is how a read-only session ends up writing.
 */
class AgentToolsExecutorTest extends TenantTestCase
{
    private const WORKER_KEY = 'test-worker-key-0123456789';

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['agent_tools.worker_key' => self::WORKER_KEY]);

        $tenant = $this->actingAsTenant('agenttools');
        $this->tenantId = $tenant->id;

        $registry = app(AgentToolRegistry::class);
        $registry->register(new StubReadTool());
        $registry->register(new StubMutatingTool());
        $registry->register(new StubPermissionedTool());
        $registry->register(new StubManyRowsTool());
        $registry->register(new StubThrowingTool());

        $this->seedCatalogue(StubReadTool::class, 'stub_read');
        $this->seedCatalogue(StubMutatingTool::class, 'stub_mutating', mutating: true);
        $this->seedCatalogue(StubPermissionedTool::class, 'stub_permissioned');
        $this->seedCatalogue(StubManyRowsTool::class, 'stub_many_rows');
        $this->seedCatalogue(StubThrowingTool::class, 'stub_throwing');
    }

    // ---------------------------------------------------------------- step 1

    public function test_the_endpoint_does_not_exist_without_a_worker_key(): void
    {
        // The shipped default. An installation that has not opted in must be
        // indistinguishable from one where the feature was never built.
        config(['agent_tools.worker_key' => '']);

        $this->callTool('stub_read', grant: $this->mintGrant())->assertNotFound();
    }

    public function test_the_worker_key_is_checked_before_the_grant_is_touched(): void
    {
        $token = $this->mintGrant();

        $this->callTool('stub_read', grant: $token, workerKey: 'wrong')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'worker_key_invalid')
            ->assertJsonPath('error.recoverable', false);

        // Step 1 rejected before step 3 ran: the counter must not exist. A
        // pipeline that increments first would let an unauthenticated caller
        // burn a real turn's budget.
        $this->assertNull(Redis::get("agent_grant:{$token}:calls"));
    }

    // ---------------------------------------------------------------- step 2

    public function test_a_missing_grant_aborts_the_turn(): void
    {
        $this->callTool('stub_read', grant: str_repeat('a', 64))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'grant_invalid')
            // Not recoverable: letting the model "handle" a dead credential
            // just burns tokens on a conversation that cannot succeed.
            ->assertJsonPath('error.recoverable', false);
    }

    // ---------------------------------------------------------------- step 3

    public function test_the_call_budget_is_enforced_atomically(): void
    {
        $token = $this->mintGrant(maxCalls: 1);

        $this->callTool('stub_read', grant: $token)->assertOk();

        $this->callTool('stub_read', grant: $token)
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'call_budget_exceeded')
            ->assertJsonPath('error.recoverable', true);
    }

    public function test_the_budget_counter_is_a_single_incr(): void
    {
        // Read-then-write would lose an update whenever one model round emits
        // two tool calls — the normal case, not an edge one. Asserting on the
        // counter is the closest a sequential test gets to proving atomicity.
        $token = $this->mintGrant(maxCalls: 5);

        $this->callTool('stub_read', grant: $token)->assertOk();
        $this->callTool('stub_read', grant: $token)->assertOk();

        $this->assertSame('2', (string) Redis::get("agent_grant:{$token}:calls"));
    }

    public function test_the_response_reports_the_remaining_budget(): void
    {
        $token = $this->mintGrant(maxCalls: 6);

        $this->callTool('stub_read', grant: $token)
            ->assertOk()
            ->assertJsonPath('calls_remaining', 5);
    }

    // ---------------------------------------------------------------- step 4

    public function test_a_tool_outside_this_turns_allowlist_is_refused(): void
    {
        // The grant, not the catalogue, is what bounds a turn — so even a fully
        // compromised worker cannot reach a tool the agent was not granted.
        $token = $this->mintGrant(tools: ['stub_read']);

        $this->callTool('stub_permissioned', grant: $token)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'tool_not_allowed')
            ->assertJsonPath('error.recoverable', true);
    }

    public function test_the_allowlist_is_checked_before_the_catalogue(): void
    {
        // A tool that does not exist AND is not granted must answer as "not
        // allowed", never "not found": the difference tells an outsider what
        // this installation has configured.
        $this->callTool('never_existed', grant: $this->mintGrant(tools: ['stub_read']))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'tool_not_allowed');
    }

    // ---------------------------------------------------------------- step 5

    public function test_an_inactive_catalogue_row_is_not_callable(): void
    {
        AgentTool::where('name', 'stub_read')->update(['is_active' => false]);

        $this->callTool('stub_read', grant: $this->mintGrant())
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'tool_not_found');
    }

    public function test_a_row_naming_an_unregistered_class_never_instantiates(): void
    {
        // The `handler` column selects among REGISTERED handlers. Resolving it
        // with app($column) would turn a text column into arbitrary class
        // instantiation.
        $this->seedCatalogue('App\\Nope\\NotRegistered', 'ghost');

        $this->callTool('ghost', grant: $this->mintGrant(tools: ['ghost']))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'tool_not_found');
    }

    // ---------------------------------------------------------------- step 6

    public function test_the_tenant_comes_from_the_grant_and_not_the_request(): void
    {
        $this->callTool('stub_read', grant: $this->mintGrant(), body: [
            'tenant_id' => 'some-other-tenant',
            'database' => 'starter_kit_tenant_victim',
        ])
            ->assertOk()
            ->assertJsonPath('result.tenant_id', $this->tenantId);
    }

    // ---------------------------------------------------------------- step 7

    public function test_a_support_session_may_still_read(): void
    {
        // Blocking reads would leave an operator unable to reproduce what the
        // customer reports, which is the reason support sessions exist.
        $this->callTool('stub_read', grant: $this->mintGrant(impersonatedBy: 'godadmin-uuid'))
            ->assertOk();
    }

    public function test_a_support_session_may_not_call_a_mutating_tool(): void
    {
        // ImpersonationGuard cannot do this for us: it returns early when there
        // is no $request->user(), and this route never has one. If this test
        // ever goes green for the wrong reason, a support session can write.
        $this->callTool('stub_mutating', grant: $this->mintGrant(
            tools: ['stub_mutating'],
            impersonatedBy: 'godadmin-uuid',
        ))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'read_only_session')
            ->assertJsonPath('error.recoverable', true);
    }

    // ---------------------------------------------------------------- step 8

    public function test_arguments_are_validated_against_the_schema(): void
    {
        $this->callTool('stub_read', grant: $this->mintGrant(), body: [], arguments: [
            'created_after' => 'yesterday',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonPath('error.recoverable', true)
            // The message must say how to fix it: the model gets one correction
            // attempt, and "invalid arguments" spends it on a guess.
            ->assertJsonFragment(['message' => 'created_after must be a date in YYYY-MM-DD format (for example 2026-08-01).']);
    }

    public function test_unknown_arguments_are_rejected(): void
    {
        $this->callTool('stub_read', grant: $this->mintGrant(), body: [], arguments: [
            'drop_table' => 'users',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    // ---------------------------------------------------------------- step 9

    public function test_a_missing_permission_is_403_and_never_500(): void
    {
        // The regression guard. AuthorizationException escaped as a 500 from
        // three controllers on 2026-08-06; here a 500 would abort the whole
        // turn instead of letting the agent explain itself.
        $admin = Admin::factory()->create(['is_super_admin' => false]);

        $this->callTool('stub_permissioned', grant: $this->mintGrant(
            tools: ['stub_permissioned'],
            actorId: $admin->id,
        ))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'permission_denied')
            ->assertJsonPath('error.recoverable', true);
    }

    public function test_an_unknown_actor_cannot_borrow_a_permission(): void
    {
        $this->callTool('stub_permissioned', grant: $this->mintGrant(
            tools: ['stub_permissioned'],
            actorId: 'no-such-admin',
        ))->assertStatus(403);
    }

    // --------------------------------------------------------- steps 10 & 11

    public function test_a_handler_that_throws_is_recoverable_and_leaks_nothing(): void
    {
        $response = $this->callTool('stub_throwing', grant: $this->mintGrant(tools: ['stub_throwing']));

        $response->assertStatus(500)
            ->assertJsonPath('error.code', 'handler_error')
            ->assertJsonPath('error.recoverable', true);

        // The message is read by the model and heard by the user. The stub
        // throws with a SQL fragment and a password in it precisely so this
        // assertion means something.
        $this->assertStringNotContainsString('hunter2', $response->getContent());
        $this->assertStringNotContainsString('SELECT', $response->getContent());
    }

    public function test_truncation_is_reported_and_the_row_cap_is_honoured(): void
    {
        $cap = (int) config('agent_tools.max_rows');

        $this->callTool('stub_many_rows', grant: $this->mintGrant(tools: ['stub_many_rows']))
            ->assertOk()
            ->assertJsonPath('truncated', true)
            ->assertJsonPath('row_count', $cap)
            ->assertJsonCount($cap, 'result');
    }

    public function test_the_catalogue_may_lower_the_row_cap_but_not_raise_it(): void
    {
        AgentTool::where('name', 'stub_many_rows')->update(['max_rows' => 5]);

        $this->callTool('stub_many_rows', grant: $this->mintGrant(tools: ['stub_many_rows']))
            ->assertOk()
            ->assertJsonPath('row_count', 5);

        AgentTool::where('name', 'stub_many_rows')->update(['max_rows' => 5000]);

        $this->callTool('stub_many_rows', grant: $this->mintGrant(tools: ['stub_many_rows']))
            ->assertOk()
            ->assertJsonPath('row_count', (int) config('agent_tools.max_rows'));
    }

    // --------------------------------------------------------------- step 12

    public function test_a_successful_call_is_audited(): void
    {
        $token = $this->mintGrant();

        $this->callTool('stub_read', grant: $token)->assertOk();

        $entry = \App\Models\AuditLog::where('action', 'agent_tool_invoked')->latest('created_at')->first();

        $this->assertNotNull($entry, 'A tool call must leave an audit row.');
        $this->assertSame('stub_read', $entry->metadata['tool']);
        $this->assertSame('ok', $entry->metadata['outcome']);
        $this->assertSame('req-under-test', $entry->metadata['request_id']);
    }

    public function test_a_refusal_is_audited_too(): void
    {
        // "The agent tried to read X and was denied" is exactly the line a
        // customer asks about. Auditing only successes answers the wrong half.
        $this->callTool('stub_permissioned', grant: $this->mintGrant(
            tools: ['stub_permissioned'],
            actorId: Admin::factory()->create(['is_super_admin' => false])->id,
        ))->assertStatus(403);

        $entry = \App\Models\AuditLog::where('action', 'agent_tool_invoked')->latest('created_at')->first();

        $this->assertNotNull($entry);
        $this->assertSame('permission_denied', $entry->metadata['outcome']);
    }

    // ------------------------------------------------------------- the grant

    public function test_a_revoked_grant_stops_working_immediately(): void
    {
        // Free revocation is the reason the grant is an opaque token rather
        // than a signed payload — a signed one stays valid until it expires.
        $token = $this->mintGrant();

        $this->callTool('stub_read', grant: $token)->assertOk();

        app(AgentGrantStoreInterface::class)->revoke($token);

        $this->callTool('stub_read', grant: $token)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'grant_invalid');
    }

    // ------------------------------------------------------------- helpers

    private function mintGrant(
        array $tools = ['stub_read', 'stub_many_rows'],
        int $maxCalls = 6,
        ?string $impersonatedBy = null,
        ?string $actorId = null,
    ): string {
        return app(AgentGrantStoreInterface::class)->issue(new AgentGrant(
            tenantId: $this->tenantId,
            database: (string) config('database.connections.tenant.database'),
            actorId: $actorId ?? Admin::factory()->create(['is_super_admin' => true])->id,
            actorType: 'admin',
            chatId: 'chat-under-test',
            agentProfileId: null,
            openaiRequestId: 'openai_test',
            requestId: 'req-under-test',
            tools: $tools,
            impersonatedBy: $impersonatedBy,
            maxCalls: $maxCalls,
            issuedAt: now()->toIso8601String(),
        ), 300);
    }

    private function callTool(
        string $name,
        string $grant,
        ?string $workerKey = null,
        array $body = [],
        array $arguments = [],
    ): TestResponse {
        return $this->postJson('/api/internal/agent/tool-call', array_merge($body, [
            'call_id' => 'call_'.uniqid(),
            'name' => $name,
            'arguments' => $arguments,
        ]), [
            'X-Agent-Worker-Key' => $workerKey ?? self::WORKER_KEY,
            'X-Agent-Grant' => $grant,
            'X-Request-Id' => 'req-under-test',
        ]);
    }

    private function seedCatalogue(string $handler, string $name, bool $mutating = false): void
    {
        AgentTool::create([
            'name' => $name,
            'handler' => $handler,
            'description' => "Stub {$name}.",
            'max_rows' => (int) config('agent_tools.max_rows'),
            'is_active' => true,
            'is_mutating' => $mutating,
        ]);
    }
}
