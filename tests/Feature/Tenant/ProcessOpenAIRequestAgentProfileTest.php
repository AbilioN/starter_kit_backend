<?php

namespace Tests\Feature\Tenant;

use App\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Jobs\ProcessOpenAIRequest;
use App\Models\Admin;
use App\Models\Assistant;
use App\Models\Chat;
use Illuminate\Support\Facades\Redis;
use Tests\TenantTestCase;

/**
 * Covers the model/system_prompt precedence ProcessOpenAIRequest now
 * resolves: a chat's specific agent profile (read live off the landlord
 * agent_profiles table) wins over the tenant's BYOK default
 * (infrastructure_providers, type=ai), which wins over null (Python
 * worker's own .env fallback). api_key always comes from BYOK regardless.
 */
class ProcessOpenAIRequestAgentProfileTest extends TenantTestCase
{
    private function setUpChatWithAssistant(?string $agentProfileId): array
    {
        $tenant = $this->actingAsTenant('acme');
        $owner = Admin::factory()->create(['is_active' => true]);

        $chat = Chat::create(['type' => 'private', 'created_by' => $owner->id]);
        $assistant = Assistant::create([
            'agent_profile_id' => $agentProfileId,
            'name' => 'Test Bot',
            'description' => 'A test bot',
            'is_active' => true,
        ]);
        \Illuminate\Support\Facades\DB::table('chat_user')->insert([
            'chat_id' => $chat->id,
            'user_id' => $assistant->id,
            'user_type' => 'assistant',
            'is_active' => true,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $chat, $assistant];
    }

    private function popLastRequestPayload(): array
    {
        $raw = Redis::rpop('openai_requests');
        $this->assertNotNull($raw, 'Expected a payload to have been pushed to openai_requests');

        return json_decode($raw, true);
    }

    protected function tearDown(): void
    {
        Redis::del('openai_requests');
        parent::tearDown();
    }

    public function test_agent_profiles_own_model_and_prompt_win_over_the_tenants_byok_default(): void
    {
        $profileId = app(AgentProfileRepositoryInterface::class)->create(
            name: 'Support Bot',
            description: null,
            avatar: null,
            systemPrompt: 'You are the agent-specific support bot.',
            model: 'gpt-4o',
        )->id;

        [$tenant, $chat] = $this->setUpChatWithAssistant($profileId);

        $byokProviderId = app(InfrastructureProviderRepositoryInterface::class)->create(
            type: 'ai',
            name: 'Tenant BYOK',
            config: ['api_key' => 'sk-tenant-key', 'model' => 'gpt-4o-mini', 'system_prompt' => 'Tenant-wide default prompt.'],
        )->id;
        app(TenantRepositoryInterface::class)->update(id: $tenant->id, aiProviderId: $byokProviderId);

        Redis::del('openai_requests');

        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $payload = $this->popLastRequestPayload();

        $this->assertSame('sk-tenant-key', $payload['api_key']);
        $this->assertSame('gpt-4o', $payload['model']);
        $this->assertSame('You are the agent-specific support bot.', $payload['system_prompt']);
    }

    public function test_falls_back_to_the_tenants_byok_default_when_the_agent_profile_has_none_set(): void
    {
        $profileId = app(AgentProfileRepositoryInterface::class)->create(
            name: 'Support Bot',
            description: null,
            avatar: null,
            systemPrompt: null,
            model: null,
        )->id;

        [$tenant, $chat] = $this->setUpChatWithAssistant($profileId);

        $byokProviderId = app(InfrastructureProviderRepositoryInterface::class)->create(
            type: 'ai',
            name: 'Tenant BYOK',
            config: ['api_key' => 'sk-tenant-key', 'model' => 'gpt-4o-mini', 'system_prompt' => 'Tenant-wide default prompt.'],
        )->id;
        app(TenantRepositoryInterface::class)->update(id: $tenant->id, aiProviderId: $byokProviderId);

        Redis::del('openai_requests');

        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $payload = $this->popLastRequestPayload();

        $this->assertSame('gpt-4o-mini', $payload['model']);
        $this->assertSame('Tenant-wide default prompt.', $payload['system_prompt']);
    }

    public function test_falls_back_to_null_when_neither_agent_profile_nor_byok_is_configured(): void
    {
        [$tenant, $chat] = $this->setUpChatWithAssistant(null);

        Redis::del('openai_requests');

        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $payload = $this->popLastRequestPayload();

        $this->assertNull($payload['api_key']);
        $this->assertNull($payload['model']);
        $this->assertNull($payload['system_prompt']);
    }
}
