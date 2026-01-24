<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Message;
use App\Application\UseCases\Chat\SendMessageToChatUseCase;
use App\Domain\Entities\ChatUserFactory;
use App\Domain\Entities\Assistant;
use Exception;

class ProcessOpenAIResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 30; // 30 seconds timeout
    public $tries = 3; // Retry 3 times if fails

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $requestId,
        private int $chatId,
        private int $userId,
        private string $response
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing OpenAI response', [
                'request_id' => $this->requestId,
                'chat_id' => $this->chatId,
                'user_id' => $this->userId,
                'response_length' => strlen($this->response)
            ]);

            // Create AI response message in the chat using SendMessageToChatUseCase
            $this->createAssistantMessage($this->chatId, $this->response);
            
            Log::info('OpenAI response processed successfully', [
                'request_id' => $this->requestId,
                'chat_id' => $this->chatId,
                'response' => $this->response
            ]);

        } catch (Exception $e) {
            Log::error('Failed to process OpenAI response', [
                'request_id' => $this->requestId,
                'chat_id' => $this->chatId,
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Parse the response string
     */
    private function parseResponse(): ?array
    {
        // Try to decode as JSON first
        $decoded = json_decode($this->response, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // It's valid JSON
            Log::info('Response parsed as JSON successfully');
            return $decoded;
        }
        
        // If not JSON, treat as plain text
        Log::info('Response treated as plain text');
        return [
            'response' => $this->response,
            'chat_id' => $this->chatId,
            'user_id' => $this->userId
        ];
    }

    /**
     * Clean up Redis after processing
     */
    private function cleanupRedis(string $responseKey): void
    {
        try {
            // Clean up the response key
            Redis::del($responseKey);
            
            // Clean up the request key
            $requestKey = "openai_request:{$this->requestId}";
            Redis::del($requestKey);
            
            Log::info('Redis cleaned up successfully', [
                'response_key' => $responseKey,
                'request_key' => $requestKey
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to cleanup Redis', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Create AI response message in the chat using SendMessageToChatUseCase
     */
    private function createAssistantMessage(int $chatId, string $content): void
    {
        try {
            Log::info('Creating assistant message', [
                'chat_id' => $chatId,
                'content' => $content
            ]);
            $assistantUser = DB::table('chat_user')
                ->where('chat_id', $chatId)
                ->where('user_type', 'assistant')
                ->where('is_active', true)
                ->first();
            if (!$assistantUser) {
                Log::warning('No active assistant found in chat_user for chat', [
                    'chat_id' => $chatId
                ]);
                // Fallback: create message directly
                $this->createAIMessageFallback($chatId, $content);
                return;
            }
            $assistantId = $assistantUser->user_id;
            Log::info('Found assistant in chat_user', [
                'chat_id' => $chatId,
                'assistant_user_id' => $assistantId
            ]);
            $assistantModel = \App\Models\Assistant::find($assistantId);
            if (!$assistantModel) {
                Log::warning('Assistant model not found for user_id', [
                    'assistant_user_id' => $assistantId,
                    'chat_id' => $chatId
                ]);
                // Fallback: create message directly
                $this->createAIMessageFallback($chatId, $content);
                return;
            }
            
            Log::info('Found assistant model', [
                'assistant_id' => $assistantId,
                'assistant_name' => $assistantModel->name,
                'assistant_capabilities' => $assistantModel->capabilities
            ]);
            $assistantChatUser = ChatUserFactory::createFromChatUserData($assistantId, 'assistant');
            $sendMessageUseCase = app(SendMessageToChatUseCase::class);
            $message = $sendMessageUseCase->execute(
                $chatId,
                $content,
                $assistantChatUser,
                'text'
            );
            Log::info('Assistant message created using SendMessageToChatUseCase', [
                'message_id' => $message->id,
                'chat_id' => $chatId,
                'assistant_id' => $assistantId,
                'assistant_name' => $assistantModel->name,
                'content' => $content
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to create assistant message using SendMessageToChatUseCase', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            $this->createAIMessageFallback($chatId, $content);
        }
    }
    
    /**
     * Fallback method to create AI message directly
     */
    private function createAIMessageFallback(int $chatId, string $content): void
    {
        Message::create([
            'chat_id' => $chatId,
            'sender_id' => $this->userId,
            'user_id' => $this->userId,
            'user_type' => 'ai',
            'content' => $content,
            'type' => 'text'
        ]);
        
        Log::info('AI message created using fallback method', [
            'chat_id' => $chatId,
            'content' => $content
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('OpenAI response job failed permanently', [
            'request_id' => $this->requestId,
            'chat_id' => $this->chatId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);
    }
}
