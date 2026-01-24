<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOpenAIRequest;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * Send a message to the chat and process with OpenAI
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'chat_id' => 'required|integer|exists:chats,id',
            'user_id' => 'required|integer',
            'message' => 'required|string|max:1000',
        ]);

        try {
            // Create the user message
            $message = Message::create([
                'chat_id' => $request->chat_id,
                'user_id' => $request->user_id,
                'user_type' => 'user',
                'content' => $request->message,
                'type' => 'text'
            ]);

            // Dispatch the OpenAI processing job
            ProcessOpenAIRequest::dispatch(
                $request->chat_id,
                $request->user_id,
                $request->message
            );

            Log::info('Message sent and OpenAI job dispatched', [
                'chat_id' => $request->chat_id,
                'user_id' => $request->user_id,
                'message_id' => $message->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => [
                    'message_id' => $message->id,
                    'status' => 'processing'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send message', [
                'error' => $e->getMessage(),
                'chat_id' => $request->chat_id,
                'user_id' => $request->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get chat messages
     */
    public function getMessages(Request $request): JsonResponse
    {
        $request->validate([
            'chat_id' => 'required|integer|exists:chats,id',
        ]);

        $messages = Message::where('chat_id', $request->chat_id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages
        ]);
    }
}
