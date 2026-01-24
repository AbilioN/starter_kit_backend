<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Application\UseCases\Auth\AdminLoginUseCase;
use App\Domain\Exceptions\AuthenticationException;
use Illuminate\Http\JsonResponse;

class AdminLoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AdminLoginRequest $request, AdminLoginUseCase $adminLoginUseCase): JsonResponse
    {
        try {
            $result = $adminLoginUseCase->execute(
                $request->email,
                $request->password
            );

            return response()->json($result, 200);
        } catch (AuthenticationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Invalid credentials'
            ], 401);
        }
    }
}
