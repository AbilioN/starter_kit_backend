<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Application\UseCases\Admin\ListUsersUseCase;
use App\Application\UseCases\Admin\GetUserUseCase;
use App\Domain\Exceptions\UserNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request, ListUsersUseCase $listUsersUseCase): JsonResponse
    {
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 15);
        
        // Validar parâmetros
        if ($page < 1) $page = 1;
        if ($perPage < 1 || $perPage > 100) $perPage = 15;

        $result = $listUsersUseCase->execute($page, $perPage);

        return response()->json($result, 200);
    }

    public function show(int $id, GetUserUseCase $getUserUseCase): JsonResponse
    {
        try {
            $result = $getUserUseCase->execute($id);

            return response()->json($result, 200);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }
}
