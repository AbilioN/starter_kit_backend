<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResendVerificationCodeRequest;
use App\Application\UseCases\Auth\ResendVerificationCodeUseCase;
use App\Domain\Exceptions\RegistrationException;
use Illuminate\Http\JsonResponse;

class ResendVerificationCodeController extends Controller
{
    public function __invoke(ResendVerificationCodeRequest $request, ResendVerificationCodeUseCase $resendVerificationCodeUseCase): JsonResponse
    {
        try {
            $result = $resendVerificationCodeUseCase->execute($request->email);

            return response()->json($result);
        } catch (RegistrationException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
} 