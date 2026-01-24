<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\Chat\GetConversationUseCase;
use App\Application\UseCases\Chat\GetChatsUseCase;
use App\Application\UseCases\Chat\SendMessageToChatUseCase;
use App\Application\UseCases\Chat\SendMessageUseCase;
use App\Domain\Entities\ChatUserFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(
        private SendMessageUseCase $sendMessageUseCase,
        private SendMessageToChatUseCase $sendMessageToChatUseCase,
        private GetConversationUseCase $getConversationUseCase,
        private GetChatsUseCase $getChatsUseCase
    ) {}

    /**
     * Enviar mensagem para outro usuário (cria ou usa chat privado)
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'other_user_id' => 'required|integer',
            'other_user_type' => 'required|in:user,admin,assistant'
        ]);

        $user = $request->user();
        $userType = $user instanceof \App\Models\Admin ? 'admin' : 'user';

        $result = $this->sendMessageUseCase->execute(
            $request->content,
            $userType,
            $user->id,
            $request->other_user_id,
            $request->other_user_type
        );

        return response()->json([
            'success' => true,
            'data' => $result
        ], 201);
    }

    /**
     * Enviar mensagem para um chat específico
     */
    public function sendMessageToChat(Request $request, int $chatId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000'
        ]);

        $user = $request->user();
        $userType = $user instanceof \App\Models\Admin ? 'admin' : 'user';

        $result = $this->sendMessageToChatUseCase->execute(
            $chatId,
            $request->content,
            $userType,
            $user->id
        );

        return response()->json([
            'success' => true,
            'data' => $result
        ], 201);
    }

    /**
     * Buscar conversa entre dois usuários
     */
    public function getConversation(Request $request, int $otherUserId, string $otherUserType): JsonResponse
    {
        $request->validate([
            'other_user_type' => 'required|in:user,admin'
        ]);

        $user = $request->user();
        $userType = $user instanceof \App\Models\Admin ? 'admin' : 'user';

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 50);

        $result = $this->getConversationUseCase->execute(
            $user->id,
            $userType,
            $otherUserId,
            $otherUserType,
            $page,
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Buscar conversas do usuário
     */
    public function getChats(Request $request): JsonResponse
    {
        $user = $request->user();
        $chatUser = ChatUserFactory::createFromModel($user);
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        $chats = $this->getChatsUseCase->execute($chatUser, $page, $perPage);
        $dto = $chats->toDto();
        return response()->json([
            'success' => true,
            'data' => $dto->toArray()
        ]);
    }
} 
 