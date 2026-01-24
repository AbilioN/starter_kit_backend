<?php

namespace App\Application\UseCases\Chat;

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
        private ChatRepositoryInterface $chatRepository
    ) {}

    public function execute(int $chatId, string $content, ChatUser $sender, string $messageType = 'text', ?array $metadata = null): Message
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
            $metadata
        );
        Log::info('Dispatching MessageSent event for message ID: ' . $message->id);
        MessageSent::dispatch($message);
        Log::info('MessageSent by sender ' . $sender->getName());
        if ($this->chatRepository->hasAssistant($chatId) && $sender->getType() !== 'assistant') {
            Log::info('Chat has assistant and sender is not assistant, dispatching OpenAI request', [
                'chat_id' => $chatId,
                'sender_type' => $sender->getType(),
                'sender_id' => $sender->getId()
            ]);
            $this->dispatchOpenAIRequest($chatId, $sender->getId(), $content);
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

    private function dispatchOpenAIRequest(int $chatId, int $userId, string $content): void
    {
        try {
            \App\Jobs\ProcessOpenAIRequest::dispatch($chatId, $userId, $content);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch OpenAI request', ['error' => $e->getMessage()]);
        }
    }
} 