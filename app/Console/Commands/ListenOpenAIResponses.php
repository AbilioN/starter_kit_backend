<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessOpenAIResponse;
use Exception;

class ListenOpenAIResponses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'listen:openai-responses {--timeout=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen continuously to OpenAI responses from Redis and process them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = $this->option('timeout');
        
        $this->info("🎧 Starting OpenAI response listener (Polling)...");
        $this->info("Checking for responses every {$timeout} seconds");
        $this->info("Press Ctrl+C to stop");

        while (true) {
            try {
                // Check for new responses in Redis
                $this->checkForResponses();
                
                // Wait before next check
                sleep($timeout);
                
            } catch (Exception $e) {
                $this->error("❌ Error in listener: " . $e->getMessage());
                Log::error('OpenAI response listener error', ['error' => $e->getMessage()]);
                
                $this->info("⏳ Waiting 5 seconds before continuing...");
                sleep(5);
                
                // Continue the loop
                continue;
            }
        }
    }

    /**
     * Check for new OpenAI responses in Redis
     */
    private function checkForResponses(): void
    {
        try {
            // Check the specific queue that Python is using
            // Note: Python sends to 'dashboard_addresses_database_openai_responses' but Laravel adds prefix
            $queueName = 'openai_responses';
            $queueLength = Redis::llen($queueName);
            
            if ($queueLength == 0) {
                $this->info("🔍 No new responses in queue: {$queueName}");
                return;
            }
            
            $this->info("📨 Found {$queueLength} response(s) in queue: {$queueName}");
            
            // Process all responses in the queue
            while ($queueLength > 0) {
                try {
                    // Get response from the queue (FIFO)
                    $response = Redis::rpop($queueName);
                    $this->info("📨 Response: " . $response);
                    
                    if ($response) {
                        $responseData = json_decode($response, true);
                        $this->info("📨 Response data: " . json_encode($responseData));
                        
                        if ($responseData && isset($responseData['id'], $responseData['chat_id'], $responseData['user_id'])) {
                            $this->info("📨 Processing response: {$responseData['id']}");
                            
                            // Use chat_id and user_id directly from the response
                            $chatId = $responseData['chat_id'];
                            $userId = $responseData['user_id'];
                            $responseText = $responseData['response'];
                            $this->info("💬 Chat ID: {$chatId}, User ID: {$userId}");

                            // tenant_id isn't trusted from whatever the Python
                            // worker echoes back - look it up from our own
                            // bookkeeping (set by ProcessOpenAIRequest).
                            $storedRequest = Redis::get("openai_request:{$responseData['id']}");
                            $stored = $storedRequest ? json_decode($storedRequest, true) : [];
                            $tenantId = $stored['tenant_id'] ?? null;

                            // Re-attach the originating request's id for this
                            // iteration only. This is a long-running process
                            // handling one response after another, so the
                            // context has to be flushed each time round or
                            // every line would be stamped with the first
                            // response's id for the life of the process.
                            Log::flushSharedContext();
                            if (! empty($stored['request_id'])) {
                                Log::shareContext(['request_id' => $stored['request_id']]);
                            }

                            // The turn is over, so its credential is spent.
                            // Revoking here rather than waiting for the TTL is
                            // the whole reason the grant is an opaque token
                            // instead of a signed payload: a signed one stays
                            // valid until it expires, whatever happens.
                            if (! empty($stored['grant_token'])) {
                                app(\App\Domain\AgentTools\AgentGrantStoreInterface::class)
                                    ->revoke($stored['grant_token']);
                            }

                            // Dispatch job to process the response with the full response data
                            ProcessOpenAIResponse::dispatch(
                                $responseData['id'],
                                $chatId,
                                $userId,
                                $responseText, // Pass the full response string
                                $tenantId,
                            );
                            
                            $this->info("✅ Job dispatched for response: {$responseData['id']}");
                            
                        } else {
                            $this->warn("⚠️ Invalid response format - missing required fields");
                            $this->warn("Required: id, chat_id, user_id");
                            $this->warn("Received: " . implode(', ', array_keys($responseData ?? [])));
                        }
                    }else{
                        $this->warn("⚠️ No response found in queue");
                    }
                    
                    $queueLength--;
                    
                } catch (Exception $e) {
                    $this->error("❌ Error processing response: " . $e->getMessage());
                    Log::error('Error processing individual response', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
        } catch (Exception $e) {
            Log::error('Error checking for OpenAI responses', ['error' => $e->getMessage()]);
            $this->error("❌ Error checking responses: " . $e->getMessage());
        }
    }
}
