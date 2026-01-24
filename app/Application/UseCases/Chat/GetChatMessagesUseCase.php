<?php

namespace App\Application\UseCases\Chat;

use App\Application\DTOs\ChatMessagesResponseDto;
use App\Application\DTOs\PaginationDto;
use App\Domain\Entities\ChatUser;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Domain\Repositories\MessageRepositoryInterface;
use Illuminate\Support\Facades\DB;

class GetChatMessagesUseCase
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository,
        private MessageRepositoryInterface $messageRepository
    ) {}

    public function execute(ChatUser $user, int $chatId, int $page = 1, int $perPage = 50): ChatMessagesResponseDto
    {
        if (!$this->chatRepository->hasParticipant($chatId, $user)) {
            throw new \Exception('Access denied', 403);
        }
        $result = $this->messageRepository->getChatMessages($chatId, $page, $perPage);
        $enrichedMessages = $this->enrichMessagesWithSenderType($result['messages'], $chatId);
        $paginationDto = PaginationDto::fromArray($result['pagination']);
        return new ChatMessagesResponseDto(
            messages: $enrichedMessages,
            fromCache: false,
            pagination: $paginationDto
        );
    }

    /**
     * Enriquece as mensagens com o tipo do remetente
     */
    private function enrichMessagesWithSenderType(array $messages, int $chatId): array
    {
        return array_map(function ($message) use ($chatId) {
            // Busca o tipo do usuário na tabela chat_user
            $senderType = DB::table('chat_user')
                ->where('chat_id', $chatId)
                ->where('user_id', $message['sender_id'])
                ->value('user_type');
            return [
                'id' => $message['id'],
                'chat_id' => $message['chat_id'],
                'content' => $message['content'],
                'sender_id' => $message['sender_id'],
                'sender_type' => $senderType ?? $message['sender_type'] ?? 'user',
                'message_type' => $message['message_type'] ?? 'text',
                'metadata' => $message['metadata'] ?? null,
                'is_read' => $message['is_read'] ?? false,
                'read_at' => $message['read_at'] ?? null,
                'created_at' => $message['created_at'] ?? null,
                'updated_at' => $message['updated_at'] ?? null
            ];
        }, $messages);
    }
}
