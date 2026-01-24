<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Application\UseCases\Auth\LoginUseCase;
use App\Domain\Exceptions\AuthenticationException;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginUseCase $loginUseCase): JsonResponse
    {
        try {
            $result = $loginUseCase->execute(
                $request->email, 
                $request->password
            );

            return response()->json($result);
        } catch (AuthenticationException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 401);
        }
    }
}
