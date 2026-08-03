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
use App\Application\UseCases\Chat\EditMessageUseCase;
use App\Application\UseCases\Chat\DeleteMessageUseCase;
use App\Application\UseCases\Chat\AddParticipantUseCase;
use App\Application\UseCases\Chat\RemoveParticipantUseCase;
use App\Application\UseCases\Chat\LeaveChatUseCase;
use App\Application\UseCases\Chat\RenameChatUseCase;
use App\Application\UseCases\User\SearchUsersUseCase;
use App\Domain\Entities\ChatUserFactory;
use App\Events\MessageRead;
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
            'receiver_id' => 'required|string'
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
            'metadata' => 'nullable|array',
            'reply_to_id' => 'nullable|uuid|exists:messages,id',
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
            $request->metadata,
            'message_processing',
            $request->reply_to_id,
            app()->bound('currentTenant') ? app('currentTenant')->id : null,
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

        broadcast(new MessageRead(
            chatId: $chatId,
            readerId: $chatUser->getId(),
            readerType: $chatUser->getType(),
        ))->toOthers();

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Messages marked as read']
        ], 200);
    }

    public function editMessage(Request $request, string $chatId, string $messageId, EditMessageUseCase $useCase): JsonResponse
    {
        $request->validate(['content' => 'required|string|max:1000']);
        $chatUser = ChatUserFactory::createFromModel($request->user());
        try {
            $result = $useCase->execute($chatUser, $chatId, $messageId, $request->content);
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function deleteMessage(Request $request, string $chatId, string $messageId, DeleteMessageUseCase $useCase): JsonResponse
    {
        $chatUser = ChatUserFactory::createFromModel($request->user());
        try {
            $useCase->execute($chatUser, $chatId, $messageId);
            return response()->json(['success' => true, 'data' => ['message' => 'Message deleted']], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function searchUsers(Request $request, SearchUsersUseCase $useCase): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:1|max:100']);
        $results = $useCase->execute($request->q);
        return response()->json(['success' => true, 'data' => $results], 200);
    }

    public function addParticipant(Request $request, string $chatId, AddParticipantUseCase $useCase): JsonResponse
    {
        $request->validate([
            'user_id'   => 'required|string',
            'user_type' => 'required|in:user,admin',
        ]);
        $chatUser = ChatUserFactory::createFromModel($request->user());
        try {
            $result = $useCase->execute($chatUser, $chatId, $request->user_id, $request->user_type);
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function removeParticipant(Request $request, string $chatId, string $userId, RemoveParticipantUseCase $useCase): JsonResponse
    {
        $chatUser = ChatUserFactory::createFromModel($request->user());
        $userType = $request->query('user_type', 'user');
        try {
            $result = $useCase->execute($chatUser, $chatId, $userId, $userType);
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function leaveChat(Request $request, string $chatId, LeaveChatUseCase $useCase): JsonResponse
    {
        $chatUser = ChatUserFactory::createFromModel($request->user());
        try {
            $result = $useCase->execute($chatUser, $chatId);
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function renameChat(Request $request, string $chatId, RenameChatUseCase $useCase): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255']);
        $chatUser = ChatUserFactory::createFromModel($request->user());
        try {
            $result = $useCase->execute($chatUser, $chatId, $request->name);
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
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
            'other_user_id' => 'required|string',
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
                'other_user_id' => 'required|string',
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
            'participants.*.user_id' => 'required|string',
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
