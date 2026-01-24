<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class JobLogger
{
    private string $jobName;
    private array $context;

    public function __construct(string $jobName, array $context = [])
    {
        $this->jobName = $jobName;
        $this->context = $context;
    }

    /**
     * Log de início do job
     */
    public function jobStarted(array $additionalContext = []): void
    {
        $this->log('🚀 ' . strtoupper($this->jobName) . ' JOB STARTED', array_merge($this->context, $additionalContext));
    }

    /**
     * Log de conclusão do job
     */
    public function jobCompleted(array $additionalContext = []): void
    {
        $this->log('🎉 ' . strtoupper($this->jobName) . ' JOB COMPLETED SUCCESSFULLY', array_merge($this->context, $additionalContext));
    }

    /**
     * Log de falha do job
     */
    public function jobFailed(string $error, array $additionalContext = []): void
    {
        $this->log('💥 ' . strtoupper($this->jobName) . ' JOB FAILED: ' . $error, array_merge($this->context, $additionalContext));
    }

    /**
     * Log de retry do job
     */
    public function jobRetrying(int $attempt, int $maxAttempts, array $additionalContext = []): void
    {
        $this->log('🔄 ' . strtoupper($this->jobName) . ' JOB RETRYING (Attempt ' . $attempt . '/' . $maxAttempts . ')', array_merge($this->context, $additionalContext));
    }

    /**
     * Log de verificação de chat
     */
    public function checkingChat(int $chatId): void
    {
        $this->log('🔍 Checking if chat exists', ['chat_id' => $chatId]);
    }

    /**
     * Log de chat encontrado
     */
    public function chatFound(int $chatId, ?string $chatName = null): void
    {
        $this->log('✅ Chat found', ['chat_id' => $chatId, 'chat_name' => $chatName ?? 'N/A']);
    }

    /**
     * Log de chat não encontrado
     */
    public function chatNotFound(int $chatId): void
    {
        $this->log('❌ Chat not found', ['chat_id' => $chatId]);
    }

    /**
     * Log de determinação de tipo de usuário
     */
    public function determiningUserType(int $userId): void
    {
        $this->log('👤 Determining user type', ['user_id' => $userId]);
    }

    /**
     * Log de tipo de usuário determinado
     */
    public function userTypeDetermined(int $userId, string $userType): void
    {
        $this->log('✅ User type determined', ['user_id' => $userId, 'user_type' => $userType]);
    }

    /**
     * Log de criação de ChatUser
     */
    public function creatingChatUser(int $userId, string $userType): void
    {
        $this->log('🏗️ Creating ChatUser entity', ['user_id' => $userId, 'user_type' => $userType]);
    }

    /**
     * Log de ChatUser criado
     */
    public function chatUserCreated(int $userId, string $userType): void
    {
        $this->log('✅ ChatUser entity created', ['user_id' => $userId, 'user_type' => $userType]);
    }

    /**
     * Log de verificação de participante
     */
    public function checkingParticipant(int $userId, int $chatId): void
    {
        $this->log('🔍 Checking if user is participant', ['user_id' => $userId, 'chat_id' => $chatId]);
    }

    /**
     * Log de participante encontrado
     */
    public function participantFound(int $userId, int $chatId): void
    {
        $this->log('✅ User is participant', ['user_id' => $userId, 'chat_id' => $chatId]);
    }

    /**
     * Log de participante não encontrado
     */
    public function participantNotFound(int $userId, int $chatId): void
    {
        $this->log('❌ User is not participant', ['user_id' => $userId, 'chat_id' => $chatId]);
    }

    /**
     * Log de processamento de mensagem
     */
    public function processingMessage(int $chatId, int $userId, string $messageType): void
    {
        $this->log('📝 Processing message with use case', ['chat_id' => $chatId, 'user_id' => $userId, 'message_type' => $messageType]);
    }

    /**
     * Log de mensagem processada
     */
    public function messageProcessed(int $chatId, int $userId, int $messageId, string $messageType): void
    {
        $this->log('✅ Message processed successfully', [
            'chat_id' => $chatId, 
            'user_id' => $userId, 
            'message_id' => $messageId,
            'message_type' => $messageType
        ]);
    }

    /**
     * Log de erro
     */
    public function error(string $message, array $additionalContext = []): void
    {
        $this->log('❌ ERROR: ' . $message, array_merge($this->context, $additionalContext));
    }

    /**
     * Log de warning
     */
    public function warning(string $message, array $additionalContext = []): void
    {
        $this->log('⚠️ WARNING: ' . $message, array_merge($this->context, $additionalContext));
    }

    /**
     * Log de info
     */
    public function info(string $message, array $additionalContext = []): void
    {
        $this->log('ℹ️ INFO: ' . $message, array_merge($this->context, $additionalContext));
    }

    /**
     * Log de debug
     */
    public function debug(string $message, array $additionalContext = []): void
    {
        $this->log('🔍 DEBUG: ' . $message, array_merge($this->context, $additionalContext));
    }

    /**
     * Método principal de logging
     */
    private function log(string $message, array $context = []): void
    {
        $fullContext = array_merge($this->context, $context, [
            'job_name' => $this->jobName,
            'timestamp' => now()->toISOString(),
        ]);

        Log::info($message, $fullContext);
    }
}
