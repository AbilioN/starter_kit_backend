<?php

namespace Tests\Feature\AgentTools;

use App\Application\AgentTools\CountUsersTool;
use App\Domain\AgentTools\AgentGrantStoreInterface;
use App\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Jobs\ProcessOpenAIRequest;
use App\Models\Admin;
use App\Models\AgentProfile;
use App\Models\AgentTool;
use App\Models\Assistant;
use App\Models\Chat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TenantTestCase;

/**
 * What ProcessOpenAIRequest puts on the wire once an agent has tools
 * (roadmap 4.11, phase 2).
 *
 * The most important test here is the first one: an agent with no tools must
 * produce a payload with **no `tools` key at all**. That is what keeps every
 * existing agent behaving exactly as it did before this feature, and what makes
 * the rollout reversible.
 */
class AgentToolPayloadTest extends TenantTestCase
{
    private const WORKER_KEY = 'payload-test-worker-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['agent_tools.worker_key' => self::WORKER_KEY]);
        Redis::del('openai_requests');
    }

    protected function tearDown(): void
    {
        Redis::del('openai_requests');

        parent::tearDown();
    }

    public function test_an_agent_without_tools_sends_no_tools_key_at_all(): void
    {
        // Not an empty array — absent. An older worker must be unable to tell
        // that this feature was deployed.
        [$tenant, $chat] = $this->chatWithAssistant($this->profileWithTools([]));

        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $payload = $this->popPayload();

        $this->assertArrayNotHasKey('tools', $payload);
        $this->assertArrayNotHasKey('tool_grant', $payload);
        $this->assertSame('You are the support bot.', $payload['system_prompt']);
    }

    public function test_the_feature_stays_off_while_no_worker_key_is_configured(): void
    {
        config(['agent_tools.worker_key' => '']);

        [$tenant, $chat] = $this->chatWithAssistant($this->profileWithTools(['count_users']));

        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        // Attached in the catalogue, but the installation has not opted in.
        $this->assertArrayNotHasKey('tools', $this->popPayload());
    }

    public function test_an_agent_with_tools_carries_them_with_a_grant(): void
    {
        [$tenant, $chat] = $this->chatWithAssistant($this->profileWithTools(['count_users']));

        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $payload = $this->popPayload();

        $this->assertSame('count_users', $payload['tools'][0]['function']['name']);
        $this->assertSame('function', $payload['tools'][0]['type']);
        $this->assertArrayHasKey('parameters', $payload['tools'][0]['function']);
        $this->assertSame((int) config('agent_tools.max_tool_calls'), $payload['max_tool_calls']);
        $this->assertSame((int) config('agent_tools.max_rounds'), $payload['max_rounds']);

        // The endpoint travels with the grant rather than being configured on
        // the worker, so the internal topology stays a server-side decision.
        $this->assertSame(config('agent_tools.endpoint'), $payload['tool_grant']['endpoint']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $payload['tool_grant']['token']);
    }

    public function test_the_grant_claims_bind_the_turn_to_one_tenant_and_one_allowlist(): void
    {
        [$tenant, $chat] = $this->chatWithAssistant($this->profileWithTools(['count_users']));

        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $payload = $this->popPayload();
        $grant = app(AgentGrantStoreInterface::class)->find($payload['tool_grant']['token']);

        $this->assertNotNull($grant);
        $this->assertSame($tenant->id, $grant->tenantId);
        $this->assertSame($tenant->database_name, $grant->database);
        // The allowlist is this turn's, not the whole catalogue's.
        $this->assertSame(['count_users'], $grant->tools);
        $this->assertSame((int) config('agent_tools.max_tool_calls'), $grant->maxCalls);
    }

    public function test_the_tool_block_is_appended_to_the_persona_never_instead_of_it(): void
    {
        // Replacing the persona would silently undo roadmap 4.3, where the
        // prompt a GodAdmin curates is the product feature.
        [$tenant, $chat] = $this->chatWithAssistant($this->profileWithTools(['count_users']));

        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $prompt = $this->popPayload()['system_prompt'];

        $this->assertStringStartsWith('You are the support bot.', $prompt);
        $this->assertStringContainsString('count_users', $prompt);
        $this->assertStringContainsString('Available tools', $prompt);
    }

    public function test_the_prompts_tool_list_is_generated_from_the_same_catalogue(): void
    {
        // A hand-written list would advertise tools this agent does not have,
        // wasting budget on refusals the executor would then issue.
        [$tenant, $chat] = $this->chatWithAssistant($this->profileWithTools(['count_users']));

        AgentTool::where('name', 'count_users')
            ->update(['description' => 'A description an operator just edited.']);

        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $payload = $this->popPayload();

        $this->assertStringContainsString('A description an operator just edited.', $payload['system_prompt']);
        $this->assertSame(
            'A description an operator just edited.',
            $payload['tools'][0]['function']['description'],
        );
    }

    public function test_a_catalogue_row_with_no_registered_handler_is_never_offered(): void
    {
        $profileId = $this->profileWithTools([]);

        $tool = AgentTool::create([
            'name' => 'ghost_tool',
            'handler' => 'App\\Nope\\NotRegistered',
            'description' => 'Names a class nobody registered.',
            'max_rows' => 10,
            'is_active' => true,
            'is_mutating' => false,
        ]);
        AgentProfile::find($profileId)->agentTools()->attach($tool->id);

        [$tenant, $chat] = $this->chatWithAssistant($profileId);

        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $this->assertArrayNotHasKey('tools', $this->popPayload());
    }

    // ------------------------------------------------------------- helpers

    private function profileWithTools(array $toolNames): string
    {
        $profileId = app(AgentProfileRepositoryInterface::class)->create(
            name: 'Support Bot',
            description: null,
            avatar: null,
            systemPrompt: 'You are the support bot.',
            model: 'gpt-4o',
        )->id;

        foreach ($toolNames as $name) {
            $tool = AgentTool::create([
                'name' => $name,
                'handler' => CountUsersTool::class,
                'description' => 'Count the users in this workspace.',
                'max_rows' => 1,
                'is_active' => true,
                'is_mutating' => false,
            ]);

            AgentProfile::find($profileId)->agentTools()->attach($tool->id);
        }

        return $profileId;
    }

    private function chatWithAssistant(string $agentProfileId): array
    {
        $tenant = $this->actingAsTenant('agentpayload');
        $owner = Admin::factory()->create(['is_active' => true]);

        $chat = Chat::create(['type' => 'private', 'created_by' => $owner->id]);
        $assistant = Assistant::create([
            'agent_profile_id' => $agentProfileId,
            'name' => 'Test Bot',
            'description' => 'A test bot',
            'is_active' => true,
        ]);

        DB::table('chat_user')->insert([
            'chat_id' => $chat->id,
            'user_id' => $assistant->id,
            'user_type' => 'assistant',
            'is_active' => true,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $chat];
    }

    private function popPayload(): array
    {
        $raw = Redis::rpop('openai_requests');
        $this->assertNotNull($raw, 'Expected a payload on openai_requests');

        return json_decode($raw, true);
    }
}
