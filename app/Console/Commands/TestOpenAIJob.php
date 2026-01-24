<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessOpenAIRequest;

class TestOpenAIJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:openai-job {chat_id} {user_id} {message}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the OpenAI job by dispatching it manually';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $chatId = $this->argument('chat_id');
        $userId = $this->argument('user_id');
        $message = $this->argument('message');

        $this->info("Dispatching OpenAI job:");
        $this->info("Chat ID: {$chatId}");
        $this->info("User ID: {$userId}");
        $this->info("Message: {$message}");

        try {
            // Dispatch the job
            ProcessOpenAIRequest::dispatch($chatId, $userId, $message);
            
            $this->info('✅ Job dispatched successfully!');
            $this->info('Check the Redis queue and your Python worker logs.');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to dispatch job: ' . $e->getMessage());
        }
    }
}
