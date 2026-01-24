<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Helpers\JobLogger;
use App\Application\UseCases\Chat\SendMessageToChatUseCase;
use App\Domain\Entities\ChatUserFactory;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Domain\Repositories\AssistantRepositoryInterface;
use Exception;

class ProcessMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60; // 1 minute timeout
    public $tries = 3; // Retry 3 times if fails
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $chatId,
        private int $userId,
        private string $userType,
        private string $content,
        private string $messageType,
        private ?array $metadata,
        private string $queueName = 'message_processing'
    ) {
        $this->onQueue($this->queueName);
    }

    /**
     * Execute the job.
     */
    public function handle(
        SendMessageToChatUseCase $useCase,
        ChatRepositoryInterface $chatRepository,
        AssistantRepositoryInterface $assistantRepository
    ): void
    {

        $logger = new JobLogger('ProcessMessageJob', [
            'chat_id' => $this->chatId,
            'user_id' => $this->userId,
            'message_type' => $this->messageType,
            'queue' => $this->queueName,
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries
        ]);

        $logger->jobStarted();

        try {
            $logger->checkingChat($this->chatId);
            $chat = $chatRepository->findById($this->chatId);
            if (!$chat) {
                $logger->chatNotFound($this->chatId);
                throw new Exception('Chat not found');
            }
            $logger->chatFound($this->chatId, $chat->name);
            $logger->determiningUserType($this->userId);
            $chatUser = ChatUserFactory::createFromChatUserData(
                $this->userId,
                $this->userType
            );

            $logger->chatUserCreated($this->userId, $this->userType);
            $logger->checkingParticipant($this->userId, $this->chatId);
            if (!$chatRepository->hasParticipant($this->chatId, $chatUser)) {
                $logger->participantNotFound($this->userId, $this->chatId);
                throw new Exception('User is no longer a participant of this chat');
            }
            $logger->participantFound($this->userId, $this->chatId);
            $logger->processingMessage($this->chatId, $this->userId, $this->messageType);

            $message = $useCase->execute(
                $this->chatId,
                $this->content,
                $chatUser,
                $this->messageType,
                $this->metadata
            );

            $logger->messageProcessed($this->chatId, $this->userId, $message->id, $this->messageType);

        } catch (Exception $e) {
            $logger->jobFailed($e->getMessage(), [
                'attempt' => $this->attempts()
            ]);
            if ($this->attempts() >= $this->tries) {
                $this->createErrorMessage();
            }

            throw $e; // Re-throw para acionar o mecanismo de retry
        }

        $logger->jobCompleted();
    }

    /**
     * Create error message in the chat
     */
    private function createErrorMessage(): void
    {
        try {
            $logger = new JobLogger('ProcessMessageJob', [
                'chat_id' => $this->chatId
            ]);
            $logger->error('Failed to process message - would create error message in chat');
        } catch (Exception $e) {
            $logger = new JobLogger('ProcessMessageJob', [
                'chat_id' => $this->chatId
            ]);
            $logger->error('Failed to create error message: ' . $e->getMessage());
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $logger = new JobLogger('ProcessMessageJob', [
            'chat_id' => $this->chatId,
            'user_id' => $this->userId,
            'message_type' => $this->messageType
        ]);

        $logger->jobFailed($exception->getMessage());

        // Cria mensagem de erro final
        $this->createErrorMessage();
    }

    /**
     * Determine the user type based on the user ID
     */
    private function determineUserType(int $userId, AssistantRepositoryInterface $assistantRepository): string
    {
        // Verifica se é um assistente
        if ($assistantRepository->findById($userId)) {
            return 'assistant';
        }
        
        // Verifica se é um admin (usando repositório quando disponível)
        // Por enquanto, mantém a verificação direta
        if (\App\Models\Admin::find($userId)) {
            return 'admin';
        }
        
        // Por padrão, assume que é um usuário normal
        return 'user';
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'message_processing',
            "chat:{$this->chatId}",
            "user:{$this->userId}",
            "type:{$this->messageType}"
        ];
    }
}
