<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessMessageJob;
use App\Models\Assistant;

class TestAssistantMessageJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:assistant-message {chat_id} {assistant_id} {content} {message_type=text} {--metadata=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending a message as an assistant using the message processing job';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $chatId = (int) $this->argument('chat_id');
        $assistantId = (int) $this->argument('assistant_id');
        $content = $this->argument('content');
        $messageType = $this->argument('message_type');
        $metadata = $this->option('metadata') ? json_decode($this->option('metadata'), true) : null;

        // Verificar se o assistente existe
        $assistant = Assistant::find($assistantId);
        if (!$assistant) {
            $this->error("❌ Assistant with ID {$assistantId} not found!");
            return 1;
        }

        if (!$assistant->isActive()) {
            $this->error("❌ Assistant {$assistant->name} is not active!");
            return 1;
        }

        $this->info("Sending message as Assistant:");
        $this->info("Chat ID: {$chatId}");
        $this->info("Assistant: {$assistant->name} (ID: {$assistantId})");
        $this->info("Content: {$content}");
        $this->info("Message Type: {$messageType}");
        $this->info("Capabilities: " . implode(', ', $assistant->getCapabilities()));
        $this->info("Metadata: " . ($metadata ? json_encode($metadata) : 'null'));

        try {
            // Disparar o job para processar a mensagem como assistente
            ProcessMessageJob::dispatch(
                $chatId,
                $assistantId,
                $content,
                $messageType,
                $metadata,
                'message_processing'
            );
            
            $this->info('✅ Assistant message job dispatched successfully!');
            $this->info('Check the queue worker logs to see the processing.');
            $this->info('Queue name: message_processing');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to dispatch assistant message job: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
