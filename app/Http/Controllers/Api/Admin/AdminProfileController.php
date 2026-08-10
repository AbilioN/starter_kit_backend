<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\UseCases\Admin\ChangeAdminPasswordUseCase;
use App\Application\UseCases\Admin\GetAdminProfileUseCase;
use App\Application\UseCases\Admin\RemoveAdminAvatarUseCase;
use App\Application\UseCases\Admin\UpdateAdminAvatarUseCase;
use App\Application\UseCases\Admin\UpdateAdminProfileUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use App\Http\Requests\Admin\UploadAdminAvatarRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProfileController extends Controller
{
    public function show(Request $request, GetAdminProfileUseCase $useCase): JsonResponse
    {
        return response()->json($useCase->execute($request->user()->id));
    }

    public function update(UpdateAdminProfileRequest $request, UpdateAdminProfileUseCase $useCase): JsonResponse
    {
        return response()->json($useCase->execute(
            $request->user()->id,
            $request->validated('name'),
            // `has()` distingue "não enviado" de "enviado como null" (= limpar).
            $request->has('notification_email'),
            $request->validated('notification_email'),
        ));
    }

    public function uploadAvatar(UploadAdminAvatarRequest $request, UpdateAdminAvatarUseCase $useCase): JsonResponse
    {
        // POST real (não PATCH com _method spoof): o PHP não parseia corpos
        // multipart num PATCH nativo.
        return response()->json($useCase->execute(
            $request->user()->id,
            $request->file('avatar'),
            app()->bound('currentTenant') ? app('currentTenant')->id : null,
        ));
    }

    public function removeAvatar(Request $request, RemoveAdminAvatarUseCase $useCase): JsonResponse
    {
        return response()->json($useCase->execute($request->user()->id));
    }

    public function changePassword(Request $request, ChangeAdminPasswordUseCase $useCase): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        try {
            $token = $request->user()->currentAccessToken();

            $result = $useCase->execute(
                $request->user()->id,
                $request->current_password,
                $request->password,
                // Mantém vivo o token de quem está a chamar e revoga os restantes:
                // matar o próprio daria 401 no request seguinte sem fluxo de
                // re-login. Um TransientToken (auth por sessão) não tem chave.
                $token instanceof \Laravel\Sanctum\PersonalAccessToken ? (string) $token->getKey() : null,
            );

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
