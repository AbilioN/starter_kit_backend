<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Message;
use App\Models\Chat;
use Exception;

class ListenOpenAIResponsesPubSub extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'listen:openai-responses-pubsub {--timeout=2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to OpenAI responses by polling Redis keys and create assistant messages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = $this->option('timeout');
        
        $this->info("🎧 Starting OpenAI response listener (Polling)...");
        $this->info("Checking for responses every {$timeout} seconds");
        $this->info("Looking for keys: openai_response:*");
        $this->info("Press Ctrl+C to stop");

        while (true) {
            try {
                $this->checkForResponses();
                sleep($timeout);
                
            } catch (Exception $e) {
                $this->error("❌ Error in listener: " . $e->getMessage());
                Log::error('OpenAI response listener error', ['error' => $e->getMessage()]);
                
                $this->info("⏳ Waiting 5 seconds before continuing...");
                sleep(5);
                
                continue;
            }
        }
    }

    /**
     * Check for new OpenAI responses in Redis keys
     */
    private function checkForResponses(): void
    {
        try {
            // Look for response keys that Python creates
            $responseKeys = Redis::keys('openai_response:*');
            
            if (empty($responseKeys)) {
                $this->info("🔍 No response keys found in Redis");
                return;
            }
            
            $this->info("📨 Found " . count($responseKeys) . " response key(s)");
            
            foreach ($responseKeys as $responseKey) {
                try {
                    $this->processResponseKey($responseKey);
                } catch (Exception $e) {
                    $this->error("❌ Error processing key {$responseKey}: " . $e->getMessage());
                    Log::error('Error processing response key', [
                        'key' => $responseKey,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
        } catch (Exception $e) {
            Log::error('Error checking for OpenAI responses', ['error' => $e->getMessage()]);
            $this->error("❌ Error checking responses: " . $e->getMessage());
        }
    }
    
    /**
     * Process a specific response key from Redis
     */
    private function processResponseKey(string $responseKey): void
    {
        $this->info("🔍 Processing response key: {$responseKey}");
        
        // Extract request ID from the key (remove 'openai_response:' prefix)
        $requestId = str_replace('openai_response:', '', $responseKey);
        
        if (!$requestId) {
            $this->warn("⚠️ Could not extract request ID from key: {$responseKey}");
            return;
        }
        
        $this->info("🔍 Processing response for request: {$requestId}");
        
        // Get the response content from Redis
        $responseContent = Redis::get($responseKey);
        
        if (!$responseContent) {
            $this->warn("⚠️ Response content not found for key: {$responseKey}");
            return;
        }
        
        $this->info("📋 Response content: " . $responseContent);
        
        // Get the original request data from Redis
        $requestData = Redis::get("openai_request:{$requestId}");
        
        if (!$requestData) {
            $this->warn("⚠️ Original request data not found for ID: {$requestId}");
            return;
        }
        
        $requestInfo = json_decode($requestData, true);
        $chatId = $requestInfo['chat_id'] ?? null;
        $userId = $requestInfo['user_id'] ?? null;
        
        if (!$chatId || !$userId) {
            $this->warn("⚠️ Missing chat_id or user_id in request data");
            return;
        }
        
        $this->info("💬 Creating assistant message for chat: {$chatId}, user: {$userId}");
        
        // Create the assistant message directly in the chat
        $this->createAssistantMessage($chatId, $userId, $responseContent);
        
        // Clean up both the response and request data
        Redis::del($responseKey);
        Redis::del("openai_request:{$requestId}");
        
        $this->info("✅ Assistant message created and Redis cleaned up!");
        
        Log::info('OpenAI response processed and assistant message created', [
            'request_id' => $requestId,
            'chat_id' => $chatId,
            'user_id' => $userId,
            'response_key' => $responseKey
        ]);
    }
    
    /**
     * Create an assistant message in the chat
     */
    private function createAssistantMessage(int $chatId, int $userId, string $content): void
    {
        try {
            // Find the assistant in this chat
            $assistant = DB::table('chat_user')
                ->where('chat_id', $chatId)
                ->where('user_type', 'assistant')
                ->where('is_active', true)
                ->first();
            
            if (!$assistant) {
                $this->warn("⚠️ No active assistant found in chat: {$chatId}");
                return;
            }
            
            $assistantId = $assistant->user_id;
            
            // Create the message as if it came from the assistant
            $message = Message::create([
                'chat_id' => $chatId,
                'sender_id' => $assistantId, // The assistant is the sender
                'user_id' => $assistantId,   // The assistant is the user
                'user_type' => 'assistant',  // This is an assistant message
                'content' => $content,
                'type' => 'text',
                'is_read' => false
            ]);
            
            $this->info("📝 Message created with ID: {$message->id}");
            
            // Dispatch event for real-time updates
            \App\Events\MessageSent::dispatch($message->toEntity());
            
            Log::info('Assistant message created', [
                'message_id' => $message->id,
                'chat_id' => $chatId,
                'assistant_id' => $assistantId,
                'content' => $content
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to create assistant message', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
