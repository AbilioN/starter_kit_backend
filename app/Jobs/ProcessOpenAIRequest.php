<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Jobs\Middleware\EstablishTenantConnection;
use App\Models\Message;
use App\Models\Chat;
use App\Models\User;
use App\Models\Assistant;
use App\Models\Tenant;
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
            $history = $this->buildHistory();

            // Prepare the request data
            $requestData = [
                'id' => $requestId,
                'chat_id' => $this->chatId,
                'user_id' => $this->userId,
                'message' => $this->userMessage,
                'tenant_id' => $this->tenantId,
                'timestamp' => now()->toISOString(),
                'status' => 'pending',
                // BYOK overrides resolved from infrastructure_providers
                // (type=ai) — all nullable, the Python worker falls back
                // to its own global .env defaults when absent.
                'api_key' => $aiConfig['api_key'] ?? null,
                'model' => $aiConfig['model'] ?? null,
                'system_prompt' => $aiConfig['system_prompt'] ?? null,
                'assistant_name' => $assistant['name'] ?? null,
                'assistant_description' => $assistant['description'] ?? null,
                'history' => $history,
            ];

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
     * Looks up the chat's active assistant participant (chat_user is the
     * source of truth for participant type, per project convention) and
     * its persona fields — used both to seed a default system prompt on
     * the Python side and to correctly attribute error messages below.
     *
     * @return array{id: ?string, name: ?string, description: ?string}
     */
    private function findActiveAssistant(): array
    {
        $chatUser = DB::table('chat_user')
            ->where('chat_id', $this->chatId)
            ->where('user_type', 'assistant')
            ->where('is_active', true)
            ->first();

        if (! $chatUser) {
            return ['id' => null, 'name' => null, 'description' => null];
        }

        $assistant = Assistant::find($chatUser->user_id);

        if (! $assistant) {
            return ['id' => $chatUser->user_id, 'name' => null, 'description' => null];
        }

        return [
            'id' => $assistant->id,
            'name' => $assistant->name,
            'description' => $assistant->description,
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
     */
    public function failed(Exception $exception): void
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
