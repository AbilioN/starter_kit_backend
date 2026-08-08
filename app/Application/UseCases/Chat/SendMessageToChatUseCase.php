<?php

namespace App\Application\UseCases\Chat;

use App\Application\UseCases\Setting\GetSettingByKeyUseCase;
use App\Domain\Entities\ChatUser;
use App\Domain\Entities\Message;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Domain\Repositories\MessageRepositoryInterface;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Log;

class SendMessageToChatUseCase
{
    public function __construct(
        private MessageRepositoryInterface $messageRepository,
        private ChatRepositoryInterface $chatRepository,
        private GetSettingByKeyUseCase $getSettingByKey,
    ) {}

    public function execute(string $chatId, string $content, ChatUser $sender, string $messageType = 'text', ?array $metadata = null, ?string $replyToId = null): Message
    {
        Log::info('Creating message', [
            'chat_id' => $chatId,
            'content' => $content,
            'sender_type' => $sender->getType(),
            'sender_id' => $sender->getId()
        ]);
        $message = $this->messageRepository->create(
            $chatId,
            $content,
            $sender,
            $messageType,
            $metadata,
            $replyToId
        );
        Log::info('Dispatching MessageSent event for message ID: ' . $message->id);
        MessageSent::dispatch($message);
        Log::info('MessageSent by sender ' . $sender->getName());
        if ($this->chatRepository->hasAssistant($chatId) && $sender->getType() !== 'assistant') {
            if (! $this->isAiAgentEnabled()) {
                Log::info('AI agent feature disabled for this tenant, skipping OpenAI dispatch', [
                    'chat_id' => $chatId,
                ]);
            } else {
                Log::info('Chat has assistant and sender is not assistant, dispatching OpenAI request', [
                    'chat_id' => $chatId,
                    'sender_type' => $sender->getType(),
                    'sender_id' => $sender->getId()
                ]);
                $this->dispatchOpenAIRequest($chatId, $sender->getId(), $content);
            }
        } else {
            Log::info('OpenAI request not dispatched', [
                'chat_id' => $chatId,
                'has_assistant' => $this->chatRepository->hasAssistant($chatId),
                'sender_type' => $sender->getType(),
                'reason' => $sender->getType() === 'assistant' ? 'Sender is assistant' : 'No assistant in chat'
            ]);
        }
        return $message;
    }

    /**
     * Tenant-level toggle (settings.features.ai_agent, surfaced in the
     * admin panel's Feature Flags page) — defaults to disabled when unset
     * (new tenants, or tenants that haven't been seeded yet).
     */
    private function isAiAgentEnabled(): bool
    {
        $setting = $this->getSettingByKey->execute('features.ai_agent');

        return (bool) ($setting['value'] ?? false);
    }

    private function dispatchOpenAIRequest(string $chatId, string $userId, string $content): void
    {
        try {
            \App\Jobs\ProcessOpenAIRequest::dispatch(
                $chatId,
                $userId,
                $content,
                'openai_requests',
                app()->bound('currentTenant') ? app('currentTenant')->id : null,
            );
        } catch (\Exception $e) {
            Log::error('Failed to dispatch OpenAI request', ['error' => $e->getMessage()]);
        }
    }
} 