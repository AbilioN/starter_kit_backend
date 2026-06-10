<?php

namespace App\Http\Controllers\Api\Auth;

use App\Application\UseCases\Auth\ForgotPasswordUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    public function __invoke(Request $request, ForgotPasswordUseCase $useCase): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        try {
            $result = $useCase->execute($request->email);
            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
