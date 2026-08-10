<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\UseCases\Assistant\GetAssistantsUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AssistantController extends Controller
{
    // No permission gating, deliberately - same access level the chat
    // widget itself already has (any authenticated admin can start a
    // private chat with any user/admin/assistant today, nothing
    // permission-gated there either). Whether AI is actually enabled for
    // this tenant is enforced separately, where it already was
    // (SendMessageToChatUseCase checks the `features.ai_agent` setting
    // before dispatching to OpenAI) - listing assistants here is harmless
    // either way.
    public function index(GetAssistantsUseCase $getAssistants): JsonResponse
    {
        $assistants = array_map(
            fn ($assistant) => $assistant->toDto(),
            $getAssistants->execute(),
        );

        return response()->json(['success' => true, 'data' => $assistants]);
    }
}
