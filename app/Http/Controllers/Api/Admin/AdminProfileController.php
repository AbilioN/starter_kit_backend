<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\UseCases\Admin\GetAdminProfileUseCase;
use App\Application\UseCases\Admin\UpdateAdminProfileUseCase;
use App\Application\UseCases\Admin\ChangeAdminPasswordUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProfileController extends Controller
{
    public function show(Request $request, GetAdminProfileUseCase $useCase): JsonResponse
    {
        return response()->json($useCase->execute($request->user()->id));
    }

    public function update(Request $request, UpdateAdminProfileUseCase $useCase): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255']);

        return response()->json($useCase->execute($request->user()->id, $request->name));
    }

    public function changePassword(Request $request, ChangeAdminPasswordUseCase $useCase): JsonResponse
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
