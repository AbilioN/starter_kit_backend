<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyEmailRequest;
use App\Application\UseCases\Auth\VerifyEmailUseCase;
use App\Domain\Exceptions\RegistrationException;
use Illuminate\Http\JsonResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(VerifyEmailRequest $request, VerifyEmailUseCase $verifyEmailUseCase): JsonResponse
    {
        try {
            $result = $verifyEmailUseCase->execute(
                $request->email,
                $request->code
            );

            return response()->json($result);
        } catch (RegistrationException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
} 