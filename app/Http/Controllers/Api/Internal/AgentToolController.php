<?php

namespace App\Http\Controllers\Api\Internal;

use App\Application\UseCases\AgentTool\ExecuteAgentToolUseCase;
use App\Domain\AgentTools\Exceptions\AgentToolFailure;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The AI worker's callback. Not part of the public API surface: it is
 * authenticated by a worker key plus a one-turn grant, never by Sanctum, and it
 * deliberately uses its own envelope (docs/11 §5) rather than the app's, because
 * the consumer is the worker's tool loop rather than a browser.
 */
class AgentToolController extends Controller
{
    public function __construct(private ExecuteAgentToolUseCase $executeAgentTool) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'call_id' => ['required', 'string', 'max:128'],
            'name' => ['required', 'string', 'max:64'],
            'arguments' => ['sometimes', 'array'],
        ]);

        try {
            $result = $this->executeAgentTool->execute(
                (string) $request->header('X-Agent-Grant', ''),
                $payload['name'],
                $payload['arguments'] ?? [],
            );
        } catch (AgentToolFailure $failure) {
            return response()->json($failure->toArray(), $failure->status);
        }

        return response()->json($result);
    }
}
