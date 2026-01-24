<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Models\Message;
use App\Models\Chat;
use App\Models\User;
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
        private int $chatId,
        private int $userId,
        private string $userMessage,
        private string $queueName = 'openai_requests'
    ) {
        //
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
            
            // Prepare the request data
            $requestData = [
                'id' => $requestId,
                'chat_id' => $this->chatId,
                'user_id' => $this->userId,
                'message' => $this->userMessage,
                'timestamp' => now()->toISOString(),
                'status' => 'pending'
            ];

            // Send request to Redis queue for Python worker
            Redis::lpush($this->queueName, json_encode($requestData));
            
            // Store request data for later retrieval when processing response
            Redis::setex("openai_request:{$requestId}", 3600, json_encode([
                'chat_id' => $this->chatId,
                'user_id' => $this->userId,
                'message' => $this->userMessage,
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
     * Create AI response message in the chat
     */
    private function createAIMessage(string $content): void
    {
        Message::create([
            'chat_id' => $this->chatId,
            'sender_id' => $this->userId, // Add sender_id
            'user_id' => $this->userId,
            'user_type' => 'ai', // Special type for AI messages
            'content' => $content,
            'type' => 'text'
        ]);
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
