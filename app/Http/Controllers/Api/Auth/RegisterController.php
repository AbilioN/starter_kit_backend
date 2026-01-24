<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Application\UseCases\Auth\RegisterUseCase;
use App\Domain\Exceptions\RegistrationException;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegisterUseCase $registerUseCase): JsonResponse
    {
        try {
            $result = $registerUseCase->execute(
                $request->name,
                $request->email, 
                $request->password
            );

            return response()->json($result, 201);
        } catch (RegistrationException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
} 