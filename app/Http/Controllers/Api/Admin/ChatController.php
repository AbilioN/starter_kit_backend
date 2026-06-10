<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Application\UseCases\Chat\GetChatsUseCase;
use App\Application\UseCases\Chat\GetConversationUseCase;
use App\Application\UseCases\Chat\SendMessageUseCase;
use App\Domain\Entities\ChatUserFactory;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function getConversations(Request $request, GetChatsUseCase $getChatsUseCase): JsonResponse
    {
        $user = $request->user();
        $chatUser = ChatUserFactory::createFromModel($user);
        $chats = $getChatsUseCase->execute($chatUser);
        $dto = $chats->toDto();

        return response()->json($dto->toArray(), 200);
    }

    public function getConversationWithUser(Request $request, GetConversationUseCase $getConversationUseCase): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|min:1',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100'
        ]);

        $admin = $request->user();

        $result = $getConversationUseCase->execute(
            $admin->id,
            'admin',
            $request->user_id,
            'user',
            $request->get('page', 1),
            $request->get('per_page', 50)
        );

        return response()->json($result, 200);
    }

    public function sendMessageToUser(Request $request, SendMessageUseCase $sendMessageUseCase): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'user_id' => 'required|integer|min:1'
        ]);

        $admin = $request->user();

        $result = $sendMessageUseCase->execute(
            $request->content,
            'admin',
            $admin->id,
            'user',
            $request->user_id
        );

        return response()->json($result, 201);
    }

    // ── Admin management views (requires chat-manage permission) ─────────────

    public function allChats(Request $request): JsonResponse
    {
        $page    = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = Chat::withCount('messages')
            ->orderByDesc('updated_at');

        if ($request->has('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(fn($chat) => [
            'id'             => $chat->id,
            'name'           => $chat->name,
            'type'           => $chat->type,
            'messages_count' => $chat->messages_count,
            'created_at'     => $chat->created_at?->format('Y-m-d H:i:s'),
            'updated_at'     => $chat->updated_at?->format('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $items,
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ], 200);
    }

    public function chatMessages(Request $request, string $chatId): JsonResponse
    {
        $chat    = Chat::findOrFail($chatId);
        $page    = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 50), 100);

        $paginator = Message::withTrashed()
            ->where('chat_id', $chatId)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        $messages = collect($paginator->items())->map(function ($msg) {
            $senderType = DB::table('chat_user')
                ->where('chat_id', $msg->chat_id)
                ->where('user_id', $msg->sender_id)
                ->value('user_type') ?? $msg->sender_type ?? 'user';

            return [
                'id'           => $msg->id,
                'chat_id'      => $msg->chat_id,
                'content'      => $msg->deleted_at ? null : $msg->content,
                'sender_id'    => $msg->sender_id,
                'sender_type'  => $senderType,
                'message_type' => $msg->message_type,
                'is_read'      => (bool) $msg->is_read,
                'edited_at'    => $msg->edited_at?->format('Y-m-d H:i:s'),
                'reply_to_id'  => $msg->reply_to_id,
                'deleted_at'   => $msg->deleted_at?->format('Y-m-d H:i:s'),
                'created_at'   => $msg->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success'    => true,
            'chat'       => ['id' => $chat->id, 'name' => $chat->name, 'type' => $chat->type],
            'data'       => $messages,
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ], 200);
    }
}
