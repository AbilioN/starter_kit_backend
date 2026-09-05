<?php

namespace Tests\Feature\AgentTools;

use App\Domain\AgentTools\AgentGrant;
use App\Domain\AgentTools\AgentGrantStoreInterface;
use App\Models\AgentDocument;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TenantTestCase;

/**
 * The end-user agent (roadmap 4.12).
 *
 * Two properties carry this design and are what these tests are for.
 *
 * **The boundary between audiences is a route, not a check.** The user endpoint
 * reads a different registry, so an admin tool asked for by name there does not
 * exist — it is not refused, which would imply something could otherwise have
 * run.
 *
 * **Self-scoping replaces the permission check.** End users have no roles, so
 * a user tool is not authorized; it is built so it cannot return anyone else's
 * data. That is architectural, so it is verified here rather than at runtime —
 * which makes these tests part of the mechanism, not a report on it.
 */
class UserAgentToolsTest extends TenantTestCase
{
    private const WORKER_KEY = 'user-tools-test-key';

    private const USER_ENDPOINT = '/api/internal/agent/user/tool-call';
    private const ADMIN_ENDPOINT = '/api/internal/agent/tool-call';

    private string $tenantId;
    private User $actor;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        config(['agent_tools.worker_key' => self::WORKER_KEY]);

        $this->tenantId = $this->actingAsTenant('useragent')->id;

        $this->actor = User::factory()->create(['name' => 'Ana Actor', 'email' => 'ana@example.test']);
        $this->other = User::factory()->create(['name' => 'Bruno Other', 'email' => 'bruno@example.test']);
    }

    // -------------------------------------------------- the route is the wall

    public function test_the_user_endpoint_does_not_know_admin_tools_exist(): void
    {
        // tool_not_found, NOT permission_denied. "Denied" would say something
        // was refused that could otherwise have run, and would tell an outsider
        // what the other endpoint holds.
        $this->callTool(self::USER_ENDPOINT, 'count_users')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'tool_not_found');
    }

    public function test_the_admin_endpoint_does_not_know_user_tools_exist(): void
    {
        $token = $this->mintGrant(actorType: 'admin', tools: ['my_profile']);

        $this->callTool(self::ADMIN_ENDPOINT, 'my_profile', grant: $token)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'tool_not_found');
    }

    public function test_a_user_grant_is_refused_by_the_admin_endpoint(): void
    {
        // The grant names its own endpoint, so this can only happen if the
        // worker was pointed at the wrong one — an internal routing error.
        $this->callTool(self::ADMIN_ENDPOINT, 'count_users')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'grant_invalid');
    }

    public function test_the_budget_is_not_spent_on_a_misrouted_grant(): void
    {
        $token = $this->mintGrant();

        $this->callTool(self::ADMIN_ENDPOINT, 'count_users', grant: $token)->assertStatus(401);

        $this->assertNull(Redis::get("agent_grant:{$token}:calls"));
    }

    // --------------------------------------------------------- self-scoping

    public function test_my_profile_returns_the_actor_and_no_one_else(): void
    {
        $response = $this->callTool(self::USER_ENDPOINT, 'my_profile')->assertOk();

        $body = $response->json();
        $this->assertSame('ana@example.test', $body['result']['email'] ?? null);
        $this->assertStringNotContainsString('bruno@example.test', $response->getContent());
    }

    public function test_my_chats_returns_only_conversations_the_actor_is_in(): void
    {
        $mine = $this->chatWith($this->actor, 'Ana and support');
        $theirs = $this->chatWith($this->other, 'Bruno and support');

        $response = $this->callTool(self::USER_ENDPOINT, 'my_chats')->assertOk();

        $this->assertStringContainsString($mine, $response->getContent());
        $this->assertStringNotContainsString($theirs, $response->getContent(),
            "A user tool returned a conversation belonging to someone else.");
    }

    public function test_my_recent_messages_refuses_a_chat_the_actor_is_not_in(): void
    {
        // The one user tool that takes an identifier from the model, and so the
        // one that has to check it before use.
        $theirs = $this->chatWith($this->other, 'Not yours');

        $this->callTool(self::USER_ENDPOINT, 'my_recent_messages', arguments: ['chat_id' => $theirs])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'permission_denied')
            ->assertJsonPath('error.recoverable', true);
    }

    public function test_my_unread_count_answers_for_the_actor(): void
    {
        $this->callTool(self::USER_ENDPOINT, 'my_unread_count')
            ->assertOk()
            ->assertJsonStructure(['result' => ['unread']]);
    }

    // ------------------------------------------------- tenant-published docs

    public function test_documents_are_shared_across_the_workspace_by_design(): void
    {
        // Not self-scoped, deliberately: the tenant publishes these FOR its
        // users, which is a different category from another user's data.
        AgentDocument::create([
            'title' => 'Guia de Início Rápido',
            'description' => 'Primeiros passos.',
            'content' => 'Para entrar, você precisa do nome do workspace da sua empresa.',
            'is_active' => true,
            // Explicit since 2026-09-06: `internal` is the default, so a
            // document reaches end users only when somebody says so.
            'audience' => AgentDocument::AUDIENCE_PUBLISHED,
        ]);

        $this->callTool(self::USER_ENDPOINT, 'list_documents')
            ->assertOk()
            ->assertJsonPath('result.0.title', 'Guia de Início Rápido');

        $this->callTool(self::USER_ENDPOINT, 'search_documents', arguments: ['query' => 'workspace'])
            ->assertOk()
            ->assertJsonPath('result.0.document', 'Guia de Início Rápido');
    }

    public function test_an_internal_document_is_invisible_to_an_end_user(): void
    {
        // The reason the audience column exists. Both document tools are
        // reachable by end users, so before this a restaurant's supplier
        // contracts would have been searchable by every customer the moment a
        // tenant could upload one.
        AgentDocument::create([
            'title' => 'Margens por fornecedor',
            'content' => 'O fornecedor A cobra 40 por cento acima do B.',
            'is_active' => true,
            'audience' => AgentDocument::AUDIENCE_INTERNAL,
        ]);

        $this->callTool(self::USER_ENDPOINT, 'search_documents', arguments: ['query' => 'fornecedor'])
            ->assertOk()
            ->assertJsonPath('row_count', 0);

        $this->callTool(self::USER_ENDPOINT, 'list_documents')
            ->assertOk()
            ->assertJsonPath('row_count', 0);
    }

    public function test_an_inactive_document_is_not_searchable(): void
    {
        AgentDocument::create([
            'title' => 'Rascunho',
            'content' => 'termosecreto',
            'is_active' => false,
        ]);

        $this->callTool(self::USER_ENDPOINT, 'search_documents', arguments: ['query' => 'termosecreto'])
            ->assertOk()
            ->assertJsonPath('row_count', 0);
    }

    // ------------------------------------------------------------- the grant

    public function test_a_deleted_actor_stops_working_immediately(): void
    {
        $token = $this->mintGrant();
        $this->callTool(self::USER_ENDPOINT, 'my_profile', grant: $token)->assertOk();

        $this->actor->delete();

        $this->callTool(self::USER_ENDPOINT, 'my_profile', grant: $token)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'permission_denied');
    }

    // -------------------------------------------------------------- helpers

    private function mintGrant(
        string $actorType = 'user',
        ?array $tools = null,
        ?string $actorId = null,
    ): string {
        return app(AgentGrantStoreInterface::class)->issue(new AgentGrant(
            tenantId: $this->tenantId,
            database: (string) config('database.connections.tenant.database'),
            actorId: $actorId ?? $this->actor->id,
            actorType: $actorType,
            chatId: 'chat-under-test',
            agentProfileId: null,
            openaiRequestId: 'openai_test',
            requestId: 'req-under-test',
            tools: $tools ?? [
                'my_profile', 'my_chats', 'my_unread_count', 'my_recent_messages',
                'my_notifications', 'list_documents', 'search_documents', 'count_users',
            ],
            impersonatedBy: null,
            maxCalls: 20,
            issuedAt: now()->toIso8601String(),
        ), 300);
    }

    private function callTool(
        string $endpoint,
        string $name,
        ?string $grant = null,
        array $arguments = [],
    ): TestResponse {
        return $this->postJson($endpoint, [
            'call_id' => 'call_'.uniqid(),
            'name' => $name,
            'arguments' => $arguments,
        ], [
            'X-Agent-Worker-Key' => self::WORKER_KEY,
            'X-Agent-Grant' => $grant ?? $this->mintGrant(),
            'X-Request-Id' => 'req-under-test',
        ]);
    }

    /**
     * A private chat with two real participants. One-sided chats do not occur in
     * production and the repository reasonably assumes a counterpart, so a
     * one-participant fixture would be testing a shape the product never has.
     */
    private function chatWith(User $user, string $name): string
    {
        $counterpart = User::factory()->create();
        $chat = Chat::create(['type' => 'private', 'name' => $name, 'created_by' => $user->id]);

        foreach ([$user, $counterpart] as $participant) {
            DB::table('chat_user')->insert([
                'chat_id' => $chat->id,
                'user_id' => $participant->id,
                'user_type' => 'user',
                'is_active' => true,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $chat->id;
    }
}
