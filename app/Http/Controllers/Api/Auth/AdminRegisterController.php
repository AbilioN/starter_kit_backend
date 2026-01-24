<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRegisterRequest;
use App\Application\UseCases\Auth\AdminRegisterUseCase;
use App\Domain\Exceptions\RegistrationException;
use Illuminate\Http\JsonResponse;

class AdminRegisterController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AdminRegisterRequest $request, AdminRegisterUseCase $adminRegisterUseCase): JsonResponse
    {
        try {
            $result = $adminRegisterUseCase->execute(
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
