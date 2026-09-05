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

    /**
     * The tenant's own standing instructions are a LAYER, not an alternative.
     *
     * This used to be `??` between the agent profile's prompt and the tenant's,
     * and since every seeded profile carries one, a tenant's own words were
     * never read at all.
     */
    public function test_the_tenants_own_instructions_are_added_to_the_operators_persona(): void
    {
        [$tenant, $chat] = $this->setUpChatWithAssistant($this->makeProfile('You are the support bot.'));

        \App\Models\Setting::updateOrCreate(
            ['key' => 'ai.instructions'],
            [
                'value' => 'We are an events venue. Never quote a price.',
                'type' => 'string', 'group' => 'ai', 'label' => 'Assistant instructions', 'is_public' => false,
            ],
        );

        Redis::del('openai_requests');
        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $prompt = $this->popLastRequestPayload()['system_prompt'];

        $this->assertStringContainsString('You are the support bot.', $prompt);
        $this->assertStringContainsString('We are an events venue.', $prompt);
        // Order is load-bearing: in a conflict the more specific instruction
        // should be the one the model read most recently.
        $this->assertLessThan(
            strpos($prompt, 'We are an events venue.'),
            strpos($prompt, 'You are the support bot.'),
        );
    }

    public function test_instructions_alone_are_enough_when_no_profile_prompt_exists(): void
    {
        [$tenant, $chat] = $this->setUpChatWithAssistant(null);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'ai.instructions'],
            [
                'value' => 'We are a restaurant. Allergen questions go to a human.',
                'type' => 'string', 'group' => 'ai', 'label' => 'Assistant instructions', 'is_public' => false,
            ],
        );

        Redis::del('openai_requests');
        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $this->assertStringContainsString(
            'We are a restaurant.',
            $this->popLastRequestPayload()['system_prompt'],
        );
    }

    public function test_blank_instructions_leave_the_prompt_exactly_as_it_was(): void
    {
        // Every tenant gets the row from the migration with a null value, so
        // the un-filled case is the common one and must change nothing.
        [$tenant, $chat] = $this->setUpChatWithAssistant($this->makeProfile('You are the support bot.'));

        \App\Models\Setting::updateOrCreate(
            ['key' => 'ai.instructions'],
            ['value' => '   ', 'type' => 'string', 'group' => 'ai', 'label' => 'Assistant instructions', 'is_public' => false],
        );

        Redis::del('openai_requests');
        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $this->assertSame('You are the support bot.', $this->popLastRequestPayload()['system_prompt']);
    }

    public function test_instructions_are_capped_before_they_reach_the_prompt(): void
    {
        // This text rides in the system prompt of EVERY message, so an
        // unbounded value is an unbounded bill and a crowded-out tool block.
        // The write path refuses beyond the cap; a seeder or a direct database
        // edit is not the write path, which is why the read caps too.
        [$tenant, $chat] = $this->setUpChatWithAssistant(null);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'ai.instructions'],
            [
                'value' => str_repeat('a', 12000),
                'type' => 'string', 'group' => 'ai', 'label' => 'Assistant instructions', 'is_public' => false,
            ],
        );

        Redis::del('openai_requests');
        ProcessOpenAIRequest::dispatchSync($chat->id, 'some-user-id', 'Hello', 'openai_requests', $tenant->id);

        $prompt = $this->popLastRequestPayload()['system_prompt'];

        $this->assertLessThanOrEqual(
            \App\Http\Requests\Admin\UpdateSettingRequest::AI_INSTRUCTIONS_MAX + 200,
            mb_strlen($prompt),
        );
    }

    public function test_internal_instructions_never_reach_an_end_user(): void
    {
        // The same change that added this gave agent_documents an
        // internal/published audience. Piping the tenant's internal guidance
        // into every turn regardless would have contradicted that in the same
        // release — and the panel's own label invites a floor price.
        [$tenant, $chat, $assistant] = $this->setUpChatWithAssistant(null);

        foreach ([
            'ai.instructions' => 'Somos a Quinta dos Eventos.',
            'ai.instructions_internal' => 'O preco minimo e 800 euros.',
        ] as $key => $value) {
            \App\Models\Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => 'string', 'group' => 'ai', 'label' => $key, 'is_public' => false],
            );
        }

        // An END USER sending the message, not an admin.
        $user = \App\Models\User::factory()->create();
        \Illuminate\Support\Facades\DB::table('chat_user')->insert([
            'chat_id' => $chat->id, 'user_id' => $user->id, 'user_type' => 'user',
            'is_active' => true, 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        Redis::del('openai_requests');
        ProcessOpenAIRequest::dispatchSync($chat->id, $user->id, 'Ola', 'openai_requests', $tenant->id);

        $prompt = (string) $this->popLastRequestPayload()['system_prompt'];

        $this->assertStringContainsString('Quinta dos Eventos', $prompt);
        $this->assertStringNotContainsString('800 euros', $prompt);
    }

    private function makeProfile(string $systemPrompt): string
    {
        return app(AgentProfileRepositoryInterface::class)->create(
            name: 'Support Bot',
            description: null,
            avatar: null,
            systemPrompt: $systemPrompt,
            model: 'gpt-4o',
        )->id;
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
