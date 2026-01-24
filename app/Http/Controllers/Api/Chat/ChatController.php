<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Application\UseCases\Chat\SendMessageUseCase;
use App\Application\UseCases\Chat\GetConversationUseCase;
use App\Application\UseCases\Chat\GetChatsUseCase;
use App\Application\UseCases\Chat\CreatePrivateChatUseCase;
use App\Application\UseCases\Chat\CreateGroupChatUseCase;
use App\Application\UseCases\Chat\SendMessageToChatUseCase;
use App\Application\UseCases\Chat\GetChatMessagesUseCase;
use App\Domain\Entities\ChatUserFactory;
use App\Jobs\ProcessMessageJob;
use App\Models\Chat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{

    public function broadcastAuth(Request $request)
    {
        return Broadcast::auth($request);
    }

    public function sendMessage(Request $request, SendMessageUseCase $sendMessageUseCase): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'receiver_type' => 'required|in:user,admin',
            'receiver_id' => 'required|integer|min:1'
        ]);

        $user = $request->user();
        $sender = ChatUserFactory::createFromModel($user);
        $receiver = ChatUserFactory::createFromChatUserData(
            $request->receiver_id,
            $request->receiver_type
        );
        $result = $sendMessageUseCase->execute(
            $request->content,
            $sender,
            $receiver
        );
        return response()->json($result, 201);
    }

    public function sendMessageToChat(Request $request, $chatId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'message_type' => 'required|in:text,image,file',
            'metadata' => 'nullable|array'
        ]);
        $user = $request->user();
        $chatUser = ChatUserFactory::createFromModel($user);
        $chat = Chat::findOrFail($chatId);
        if (!$chat->hasParticipant($chatUser)) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        $chatUserType = $chatUser->getType();
        ProcessMessageJob::dispatchSync(
            $chatId,
            $chatUser->getId(),
            $chatUserType,
            $request->content,
            $request->message_type,
            $request->metadata
        );
        return response()->json([
            'success' => true,
            'message' => 'Message queued for processing',
            'data' => [
                'chat_id' => $chatId,
                'status' => 'queued',
                'message_type' => $request->message_type
            ]
        ], 202);
    }

    public function getChatMessages(Request $request, $chatId, GetChatMessagesUseCase $useCase): JsonResponse
    {
        $user = $request->user();
        $chatUser = ChatUserFactory::createFromModel($user);
        try {
            // Sempre pegar as 30 mensagens mais recentes
            $result = $useCase->execute($chatUser, $chatId, 1, 30);
            return response()->json($result->toArray(), 200);
        } catch (\Exception $e) {
            if ($e->getCode() === 403) {
                return response()->json(['error' => 'Access denied'], 403);
            }
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function markMessagesAsRead(Request $request, $chatId): JsonResponse
    {
        $user = $request->user();
        $chatUser = ChatUserFactory::createFromModel($user);
        $chat = Chat::findOrFail($chatId);
        if (!$chat->hasParticipant($chatUser)) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        $chat->markAsReadForChatUser($chatUser);
        return response()->json([
            'success' => true,
            'data' => ['message' => 'Messages marked as read']
        ], 200);
    }

    public function getUnreadCount(Request $request, $chatId): JsonResponse
    {
        $user = $request->user();
        $chatUser = ChatUserFactory::createFromModel($user);

        // Verifica se o usuário é participante do chat
        $chat = Chat::findOrFail($chatId);
        if (!$chat->hasParticipant($chatUser)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Conta mensagens não lidas (enviadas por outros usuários)
        $unreadCount = $chat->messages()
            ->where('sender_id', '!=', $chatUser->getId())
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $unreadCount]
        ], 200);
    }

    public function getConversation(Request $request, GetConversationUseCase $getConversationUseCase): JsonResponse
    {
        $request->validate([
            'other_user_type' => 'required|in:user,admin',
            'other_user_id' => 'required|integer|min:1',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100'
        ]);

        $user = $request->user();
        $chatUser = ChatUserFactory::createFromModel($user);
        $otherChatUser = ChatUserFactory::createFromChatUserData(
            $request->other_user_id,
            $request->other_user_type
        );
        $result = $getConversationUseCase->execute(
            $chatUser,
            $otherChatUser,
            $request->get('page', 1),
            $request->get('per_page', 50)
        );

        return response()->json($result, 200);
    }

    public function getChats(Request $request, GetChatsUseCase $getChatsUseCase): JsonResponse
    {
        $user = $request->user();
        $chatUser = ChatUserFactory::createFromModel($user);
        $chats = $getChatsUseCase->execute($chatUser);

        // Convert domain entity to DTO for API response
        $dto = $chats->toDto();
        
        return response()->json($dto->toArray(), 200);
    }

    public function createPrivateChat(Request $request, CreatePrivateChatUseCase $useCase): JsonResponse
    {
        try {
            DB::beginTransaction();
            $request->validate([
                'other_user_id' => 'required|integer',
                'other_user_type' => 'required|in:user,admin,assistant'
            ]);
    
            $user = $request->user();
            $chatUser = ChatUserFactory::createFromModel($user);
            $otherChatUser = ChatUserFactory::createFromChatUserData(
                $request->other_user_id,
                $request->other_user_type
            );
            $chat = $useCase->execute($chatUser, $otherChatUser);
            DB::commit();   
            return response()->json([
                'success' => true, 
                'data' => $chat->toDto()->toArray()
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }


    }

    public function createGroupChat(Request $request, CreateGroupChatUseCase $useCase): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'participants' => 'required|array|min:1',
            'participants.*.user_id' => 'required|integer',
            'participants.*.user_type' => 'required|in:user,admin'
        ]);

        $user = $request->user();
        $chatUser = ChatUserFactory::createFromModel($user);
        // Converte participantes para ChatUsers
        $participants = collect($request->participants)->map(function ($participant) {
            return ChatUserFactory::createFromChatUserData(
                $participant['user_id'],
                $participant['user_type']
            );
        })->toArray();

        $chat = $useCase->execute(
            $chatUser,
            $request->name,
            $request->description,
            $participants
        );

        return response()->json([
            'success' => true, 
            'data' => $chat->toDto()->toArray()
        ], 201);
    }
}
