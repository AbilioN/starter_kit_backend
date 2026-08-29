<?php

namespace App\Http\Controllers\Api\Internal;

use App\Application\UseCases\AgentTool\ExecuteUserAgentToolUseCase;
use App\Domain\AgentTools\Exceptions\AgentToolFailure;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The AI worker's callback for an END USER's turn.
 *
 * A separate route from the admin one, not a parameter on it. It resolves tools
 * from a different registry, so it cannot reach an admin tool even if asked by
 * name — the separation is structural rather than a check (docs/15 §4).
 *
 * Same worker key, same grant, same envelope: which endpoint a turn uses is
 * decided server-side at mint time, so the worker never learns what kind of
 * person started it.
 */
class UserAgentToolController extends Controller
{
    public function __construct(private ExecuteUserAgentToolUseCase $executeUserAgentTool) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'call_id' => ['required', 'string', 'max:128'],
            'name' => ['required', 'string', 'max:64'],
            'arguments' => ['sometimes', 'array'],
        ]);

        try {
            $result = $this->executeUserAgentTool->execute(
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
