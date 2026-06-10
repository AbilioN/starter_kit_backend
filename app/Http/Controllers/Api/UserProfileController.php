<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\User\GetUserProfileUseCase;
use App\Application\UseCases\User\UpdateUserProfileUseCase;
use App\Application\UseCases\User\ChangeUserPasswordUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function show(Request $request, GetUserProfileUseCase $useCase): JsonResponse
    {
        return response()->json($useCase->execute($request->user()->id));
    }

    public function update(Request $request, UpdateUserProfileUseCase $useCase): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255']);

        return response()->json($useCase->execute($request->user()->id, $request->name));
    }

    public function changePassword(Request $request, ChangeUserPasswordUseCase $useCase): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        try {
            $result = $useCase->execute(
                $request->user()->id,
                $request->current_password,
                $request->password,
            );
            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
