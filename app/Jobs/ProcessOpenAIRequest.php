<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use App\Domain\Repositories\SettingRepositoryInterface;
use App\Http\Requests\Admin\UpdateSettingRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Jobs\Middleware\EstablishTenantConnection;
use App\Models\Message;
use App\Models\Chat;
use App\Models\User;
use App\Models\Assistant;
use App\Models\AgentProfile;
use App\Models\Tenant;
use App\Application\UseCases\AgentTool\ComposeAgentSystemPromptUseCase;
use App\Application\UseCases\AgentTool\IssueAgentGrantUseCase;
use App\Application\UseCases\AgentTool\ResolveAgentToolsUseCase;
use App\Application\UseCases\AgentTool\ResolveUserAgentToolsUseCase;
use App\Domain\AgentTools\AgentGrant;
use App\Domain\Services\TenantInfrastructureResolverInterface;
use App\Events\MessageSent;
use Exception;

class ProcessOpenAIRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes timeout
    public $tries = 3; // Retry 3 times if fails

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $chatId,
        private string $userId,
        private string $userMessage,
        private string $queueName = 'openai_requests',
        private ?string $tenantId = null,
    ) {
        //
    }

    public function middleware(): array
    {
        return [new EstablishTenantConnection($this->tenantId)];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing OpenAI request via Redis', [
                'chat_id' => $this->chatId,
                'user_id' => $this->userId,
                'message' => $this->userMessage
            ]);

            // Create a unique request ID
            $requestId = uniqid('openai_', true);

            $assistant = $this->findActiveAssistant();
            $aiConfig = $this->resolveAiConfig();
            $agentProfileConfig = $this->resolveAgentProfileConfig($assistant['agent_profile_id'] ?? null);
            $history = $this->buildHistory();

            // Agent tools (roadmap 4.11). Read live from the landlord catalogue,
            // same as the agent profile above — attaching a tool takes effect on
            // the very next message. Empty whenever the feature is off, the
            // agent has no profile, or nothing is attached.
            // Which set depends on who is asking. An admin's is curated per
            // agent profile; an end user's is static (docs/15 §3). The actor
            // type comes from chat_user, the source of truth for participant
            // type — never from the message.
            $actorType = $this->resolveActorType();
            $tools = $actorType === 'user'
                ? app(ResolveUserAgentToolsUseCase::class)->execute()
                : app(ResolveAgentToolsUseCase::class)->execute($assistant['agent_profile_id'] ?? null);
            // Three sources, LAYERED rather than chosen between. It used to be
            // `??`, which made them alternatives — and since every seeded
            // agent profile carries a prompt, a tenant's own instructions were
            // never read at all. Order is not arbitrary: the operator's
            // persona carries the platform's rules and goes first; the
            // tenant's go after, because in a conflict the more specific
            // instruction should be the one the model read most recently.
            $persona = $this->layerPersona(
                $agentProfileConfig['system_prompt'] ?? $aiConfig['system_prompt'] ?? null,
                $this->tenantInstructions($actorType),
            );

            // Prepare the request data
            $requestData = [
                'id' => $requestId,
                'chat_id' => $this->chatId,
                'user_id' => $this->userId,
                'message' => $this->userMessage,
                'tenant_id' => $this->tenantId,
                'timestamp' => now()->toISOString(),
                'status' => 'pending',
                // The HTTP request that started this. Carried into the worker
                // by ObservabilityServiceProvider, so it is already in the
                // shared log context here — no constructor argument needed.
                // The Python worker logs it, and it is stored below so the
                // response leg picks it up again: one id across Laravel,
                // Python and back.
                'request_id' => Log::sharedContext()['request_id'] ?? null,
                // api_key never comes from an agent profile — it's always
                // the tenant's own BYOK credential (infrastructure_providers,
                // type=ai) or null (Python worker's global .env default).
                'api_key' => $aiConfig['api_key'] ?? null,
                // Where to spend it. Travels with the key because the two are
                // one decision: a tenant pointed at a self-hosted model has a
                // different endpoint AND a different credential (usually
                // none). Null lets the worker's own .env decide, which is how
                // a local development stack runs the whole agent for free.
                'base_url' => $aiConfig['base_url'] ?? null,
                // model/system_prompt: this specific agent's own profile
                // wins when set (read live from the landlord agent_profiles
                // table, never copied - an edit there takes effect on the
                // very next message), else the tenant-wide BYOK default,
                // else null (Python worker falls back further: its own
                // .env model, and assistant_name/description below for the
                // prompt).
                //
                // UNLESS the tenant named its own endpoint. A model name
                // belongs to an endpoint: every profile this kit seeds pins an
                // OpenAI name, and asking a self-hosted vLLM for `gpt-4o-mini`
                // is a 404 on every single turn. Worse, silently — a 404 is
                // not in the worker's unrecoverable set, so the user is told
                // to try again forever and readiness stays green. base_url and
                // model come from the same provider row and resolving them
                // with opposite precedence is what made that reachable.
                'model' => isset($aiConfig['base_url'])
                    ? ($aiConfig['model'] ?? $agentProfileConfig['model'] ?? null)
                    : ($agentProfileConfig['model'] ?? $aiConfig['model'] ?? null),
                'system_prompt' => $this->composeSystemPrompt($persona, $assistant, $tools['specs']),
                'assistant_name' => $assistant['name'] ?? null,
                'assistant_description' => $assistant['description'] ?? null,
                'history' => $history,
            ];

            // The one-turn credential. Minted only when this agent actually has
            // tools: without these keys the payload is byte-identical to one
            // from before agent tools existed, which is what makes the rollout
            // safe and an older worker's behaviour unchanged.
            $grantToken = null;

            if ($tools['specs'] !== []) {
                $grant = $this->issueGrant($requestId, $assistant, $tools['names']);

                if ($grant !== null) {
                    $grantToken = $grant['token'];
                    $requestData['tools'] = $tools['specs'];
                    $requestData['tool_grant'] = $grant;
                    $requestData['max_tool_calls'] = (int) config('agent_tools.max_tool_calls');
                    $requestData['max_rounds'] = (int) config('agent_tools.max_rounds');
                }
            }

            // Send request to Redis queue for Python worker
            Redis::lpush($this->queueName, json_encode($requestData));

            // Store request data for later retrieval when processing response.
            // tenant_id is looked up from here (not trusted from whatever the
            // Python worker echoes back) so ListenOpenAIResponses can
            // re-establish the correct tenant connection before dispatching
            // ProcessOpenAIResponse.
            Redis::setex("openai_request:{$requestId}", 3600, json_encode([
                'chat_id' => $this->chatId,
                'user_id' => $this->userId,
                'message' => $this->userMessage,
                'tenant_id' => $this->tenantId,
                // Read back by ListenOpenAIResponses rather than trusted from
                // what the worker echoes, for the same reason as tenant_id.
                'request_id' => Log::sharedContext()['request_id'] ?? null,
                // Read back by ListenOpenAIResponses, which revokes the grant
                // the moment the reply lands — so the normal lifetime of a
                // credential is seconds rather than its full TTL.
                'grant_token' => $grantToken,
                'timestamp' => now()->toISOString()
            ]));

            Log::info('Request sent to Redis queue', [
                'request_id' => $requestId,
                'queue' => $this->queueName
            ]);

            // The Python worker will send the response back to Redis
            // and our listener will pick it up and process it

        } catch (Exception $e) {
            Log::error('Failed to process OpenAI request', [
                'chat_id' => $this->chatId,
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);

            // Create error message in chat
            $this->createAIMessage('Sorry, I encountered an error processing your request. Please try again later.');

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Persona first, tool block second — never the other way round, and never
     * instead of. See ComposeAgentSystemPromptUseCase.
     *
     * The name/description fallback is applied HERE rather than left to the
     * worker: once a tool block exists, `system_prompt` is non-null, and the
     * worker's own fallback would never fire — silently dropping the assistant's
     * persona from every tool-enabled agent.
     */
    /**
     * The tenant's own standing instructions — "we are an events venue, never
     * quote a price, the small hall seats 40".
     *
     * A tenant setting rather than the `ai` infrastructure provider config,
     * because this is the tenant's to write: which GPU answers them is an
     * operational decision with a bill attached, and lives GodAdmin-side.
     *
     * Capped when it is written, not here — but read defensively anyway, since
     * an un-migrated tenant simply has no row and must behave exactly as it
     * did before this existed.
     */
    private function tenantInstructions(string $actorType): ?string
    {
        // `ai.instructions` reaches everyone the assistant serves.
        // `ai.instructions_internal` is added only for STAFF turns — the same
        // split `agent_documents.audience` makes, and for the same reason: the
        // panel invites "what the assistant must never do", and a tenant will
        // reasonably write a floor price there. Anything that is not an admin
        // is treated as an end user, fail-closed, so a future actor type
        // nobody updated this for sees the public half only.
        $keys = $actorType === 'admin'
            ? ['ai.instructions', 'ai.instructions_internal']
            : ['ai.instructions'];

        $parts = [];

        foreach ($keys as $key) {
            $value = $this->settingValue($key);

            if ($value !== null) {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    private function settingValue(string $key): ?string
    {
        try {
            $value = app(SettingRepositoryInterface::class)->findByKey($key)?->value;
        } catch (\Throwable $e) {
            // A missing table on a tenant nobody has migrated yet must not
            // take the whole turn down; the assistant simply answers without
            // the tenant's own voice.
            Log::warning('could not read an ai instruction setting', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // A hard ceiling, because this text is concatenated into the system
        // prompt of every single message. The request that writes it refuses
        // beyond this, but a seeder, a console command or a direct database
        // edit is not that request — and the failure here would be a quietly
        // enormous prompt on every turn rather than an error anyone sees.
        if (mb_strlen($value) > UpdateSettingRequest::AI_INSTRUCTIONS_MAX) {
            Log::warning('an ai instruction setting exceeds the cap and was truncated for this turn', [
                'key' => $key,
                'length' => mb_strlen($value),
                'cap' => UpdateSettingRequest::AI_INSTRUCTIONS_MAX,
            ]);

            $value = mb_substr($value, 0, UpdateSettingRequest::AI_INSTRUCTIONS_MAX);
        }

        return $value;
    }

    /** Neither layer may overwrite the other. */
    private function layerPersona(?string $operatorPersona, ?string $tenantInstructions): ?string
    {
        $parts = array_filter([
            $operatorPersona !== null && trim($operatorPersona) !== '' ? trim($operatorPersona) : null,
            $tenantInstructions,
        ]);

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    private function composeSystemPrompt(?string $persona, array $assistant, array $toolSpecs): ?string
    {
        if ($toolSpecs === []) {
            return $persona;
        }

        if (($persona === null || trim($persona) === '') && ! empty($assistant['name'])) {
            $persona = trim("You are {$assistant['name']}. ".($assistant['description'] ?? ''));
        }

        return app(ComposeAgentSystemPromptUseCase::class)->execute($persona, $toolSpecs);
    }

    /**
     * @param  array<int, string>  $toolNames  this turn's allowlist
     * @return array{token: string, endpoint: string, expires_at: string}|null
     */
    private function issueGrant(string $requestId, array $assistant, array $toolNames): ?array
    {
        // No tenant means no database claim to resolve, and the tenant is the
        // one thing a grant must never be vague about. Console-dispatched jobs
        // land here; they simply get no tools.
        if (! $this->tenantId) {
            return null;
        }

        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            return null;
        }

        return app(IssueAgentGrantUseCase::class)->execute(new AgentGrant(
            tenantId: (string) $this->tenantId,
            database: (string) $tenant->database_name,
            actorId: $this->userId,
            actorType: $this->resolveActorType(),
            chatId: $this->chatId,
            agentProfileId: $assistant['agent_profile_id'] ?? null,
            openaiRequestId: $requestId,
            requestId: (string) (Log::sharedContext()['request_id'] ?? ''),
            tools: $toolNames,
            // Carried through the queue by ObservabilityServiceProvider from
            // ImpersonationGuard, exactly like request_id. Without it a support
            // session could be laundered into a write once mutating tools exist.
            impersonatedBy: Log::sharedContext()['impersonated_by'] ?? null,
            maxCalls: (int) config('agent_tools.max_tool_calls'),
            issuedAt: now()->toIso8601String(),
        ));
    }

    /**
     * chat_user.user_type is the source of truth for participant type — never
     * messages.sender_type. Defaults to 'admin' because the AI chat is an
     * admin-panel surface today; a wrong guess here would authorize the turn
     * against the wrong table.
     */
    private function resolveActorType(): string
    {
        $type = DB::table('chat_user')
            ->where('chat_id', $this->chatId)
            ->where('user_id', $this->userId)
            ->where('is_active', true)
            ->value('user_type');

        return in_array($type, ['admin', 'user'], true) ? $type : 'admin';
    }

    /**
     * Looks up the chat's active assistant participant (chat_user is the
     * source of truth for participant type, per project convention) and
     * its persona fields — used both to seed a default system prompt on
     * the Python side and to correctly attribute error messages below.
     *
     * @return array{id: ?string, name: ?string, description: ?string, agent_profile_id: ?string}
     */
    private function findActiveAssistant(): array
    {
        $chatUser = DB::table('chat_user')
            ->where('chat_id', $this->chatId)
            ->where('user_type', 'assistant')
            ->where('is_active', true)
            ->first();

        if (! $chatUser) {
            return ['id' => null, 'name' => null, 'description' => null, 'agent_profile_id' => null];
        }

        $assistant = Assistant::find($chatUser->user_id);

        if (! $assistant) {
            return ['id' => $chatUser->user_id, 'name' => null, 'description' => null, 'agent_profile_id' => null];
        }

        return [
            'id' => $assistant->id,
            'name' => $assistant->name,
            'description' => $assistant->description,
            'agent_profile_id' => $assistant->agent_profile_id,
        ];
    }

    /**
     * Reads this chat's assistant's own persona straight off the landlord
     * agent_profiles table at send time — deliberately not cached/copied
     * anywhere, so a GodAdmin editing a profile's prompt/model takes effect
     * on the very next message, no propagation step involved. Runs on the
     * `landlord` connection explicitly (AgentProfile's own $connection),
     * same reasoning as resolveAiConfig() above.
     *
     * @return array{model: ?string, system_prompt: ?string}|null
     */
    private function resolveAgentProfileConfig(?string $agentProfileId): ?array
    {
        if (! $agentProfileId) {
            return null;
        }

        $profile = AgentProfile::find($agentProfileId);

        if (! $profile || ! $profile->is_active) {
            return null;
        }

        return [
            'model' => $profile->model,
            'system_prompt' => $profile->system_prompt,
        ];
    }

    /**
     * BYOK: resolve this tenant's own OpenAI api key/model/system prompt
     * override (infrastructure_providers, type=ai), falling back through
     * the tenant's subscription plan default, else null (Python worker
     * uses its own global .env default in that case). Runs against the
     * `landlord` connection explicitly (Tenant's own $connection), so it's
     * unaffected by EstablishTenantConnection having already pointed
     * database.default at the tenant DB for the rest of this job.
     */
    private function resolveAiConfig(): ?array
    {
        if (! $this->tenantId) {
            return null;
        }

        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            return null;
        }

        return app(TenantInfrastructureResolverInterface::class)->resolveAiConfig($tenant->toEntity());
    }

    /**
     * Last 10 messages of the chat, oldest first, excluding the
     * just-created current message (already sent separately as `message`)
     * — gives the AI multi-turn context instead of the legacy
     * single-turn/stateless behaviour.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function buildHistory(): array
    {
        return Message::where('chat_id', $this->chatId)
            ->orderBy('created_at', 'desc')
            ->limit(11)
            ->get()
            ->skip(1)
            ->reverse()
            ->values()
            ->map(fn (Message $message) => [
                'role' => $message->sender_type === 'assistant' ? 'assistant' : 'user',
                'content' => $message->content,
            ])
            ->all();
    }

    /**
     * Create AI response message in the chat (used on failure — a real
     * assistant reply is created via SendMessageToChatUseCase by
     * ProcessOpenAIResponse on the success path, not here).
     */
    private function createAIMessage(string $content): void
    {
        $assistant = $this->findActiveAssistant();

        if (! $assistant['id']) {
            Log::error('Cannot create AI error message: no assistant participant in chat', [
                'chat_id' => $this->chatId,
            ]);
            return;
        }

        $message = Message::create([
            'chat_id' => $this->chatId,
            'sender_id' => $assistant['id'],
            'sender_type' => 'assistant',
            'content' => $content,
            'message_type' => 'text',
        ]);

        MessageSent::dispatch($message->toEntity());
    }

    /**
     * Handle a job failure.
     *
     * Typed Throwable, not Exception: Laravel passes whatever was thrown, and a
     * TypeError is an Error. A narrower signature here replaces the real
     * failure with a confusing one about this method's own argument — which is
     * exactly what happened while building agent tools.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('OpenAI request job failed permanently', [
            'chat_id' => $this->chatId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);

        // Create error message in chat
        $this->createAIMessage('Sorry, I was unable to process your request. Please try again later.');
    }
}
