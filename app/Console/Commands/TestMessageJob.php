<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessMessageJob;

class TestMessageJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:message-job {chat_id} {user_id} {content} {message_type=text} {--metadata=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the message processing job by dispatching it manually';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $chatId = (int) $this->argument('chat_id');
        $userId = (int) $this->argument('user_id');
        $content = $this->argument('content');
        $messageType = $this->argument('message_type');
        $metadata = $this->option('metadata') ? json_decode($this->option('metadata'), true) : null;

        $this->info("Dispatching Message Processing Job:");
        $this->info("Chat ID: {$chatId}");
        $this->info("User ID: {$userId}");
        $this->info("Content: {$content}");
        $this->info("Message Type: {$messageType}");
        $this->info("Metadata: " . ($metadata ? json_encode($metadata) : 'null'));

        try {
            // Dispatch the job
            ProcessMessageJob::dispatch($chatId, $userId, $content, $messageType, $metadata);
            
            $this->info('✅ Message job dispatched successfully!');
            $this->info('Check the queue worker logs to see the processing.');
            $this->info('Queue name: message_processing');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to dispatch message job: ' . $e->getMessage());
        }
    }
}
