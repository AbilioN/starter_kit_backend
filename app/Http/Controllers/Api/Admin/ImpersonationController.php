<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\UseCases\GodAdmin\StopImpersonationUseCase;
use App\Http\Controllers\Controller;
use App\Models\GodAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets the admin panel know it is running inside a GodAdmin support session,
 * and lets the operator end it.
 *
 * The panel needs this to show the banner. A support session that looks
 * exactly like a normal login is how an operator forgets they are inside a
 * customer's account.
 */
class ImpersonationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if (! $token || ! $token->impersonated_by) {
            return response()->json(['success' => true, 'data' => ['active' => false]]);
        }

        // GodAdmin lives on the landlord connection, which stays reachable
        // while `database.default` points at the tenant — the model declares
        // its own connection.
        $godAdmin = GodAdmin::find($token->impersonated_by);

        return response()->json([
            'success' => true,
            'data' => [
                'active' => true,
                'operator' => $godAdmin?->email ?? (string) $token->impersonated_by,
                'can_write' => (bool) $token->can('impersonation:write'),
                'expires_at' => $token->expires_at?->toISOString(),
                'admin_name' => $request->user()->name,
            ],
        ]);
    }

    public function stop(Request $request, StopImpersonationUseCase $stopImpersonation): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if (! $token || ! $token->impersonated_by) {
            return response()->json([
                'success' => false,
                'message' => 'This session is not a support session.',
            ], 400);
        }

        $stopImpersonation->execute($request->user(), $token);

        return response()->json(['success' => true, 'data' => ['active' => false]]);
    }
}
