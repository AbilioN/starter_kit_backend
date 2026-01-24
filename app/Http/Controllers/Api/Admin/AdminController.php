<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\AssignRoleToAdminUseCase;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\Admin\CreateAdminUseCase;
use App\Application\UseCases\Admin\DeleteAdminUseCase;
use App\Application\UseCases\Admin\GetAllAdminsUseCase;
use App\Application\UseCases\Admin\UpdateAdminUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAdminRequest;
use App\Http\Requests\UpdateAdminRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private GetAllAdminsUseCase $getAllAdminsUseCase,
        private CreateAdminUseCase $createAdminUseCase,
        private UpdateAdminUseCase $updateAdminUseCase,
        private DeleteAdminUseCase $deleteAdminUseCase,
        private AssignRoleToAdminUseCase $assignRoleToAdminUseCase,
        private AuthorizeActionUseCase $authorizeActionUseCase
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $admin = AdminFactory::createFromModel($adminModel);
            $this->authorizeActionUseCase->execute($admin, 'admin-read');
            
            // Obter parâmetros de paginação da query string
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 15);
            $search = $request->query('search');
            $isActive = $request->query('is_active');
            
            // Converter string 'true'/'false' para boolean
            if ($isActive !== null && $isActive !== '') {
                $isActive = filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            } else {
                $isActive = null;
            }
            
            // Validar limites
            $perPage = min(max($perPage, 1), 100); // Entre 1 e 100
            $page = max($page, 1);
            
            // Executar use case com paginação
            $result = $this->getAllAdminsUseCase->executePaginated(
                $page, 
                $perPage,
                $search,
                $isActive
            );
            
            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to']
                ]
            ], 200);
        } catch (\App\Domain\Exceptions\AuthorizationException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function create(CreateAdminRequest $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $admin = AdminFactory::createFromModel($adminModel);
            
            $this->authorizeActionUseCase->execute($admin, 'admin-create');
            
            $newAdmin = $this->createAdminUseCase->execute(
                $request->input('name'),
                $request->input('email'),
                $request->input('password'),
                $request->input('is_active', true)
            );
            
            // Se role_id foi fornecido, atribuir a role ao admin
            if ($request->has('role_id')) {
                $this->assignRoleToAdminUseCase->execute(
                    $newAdmin['id'],
                    $request->input('role_id'),
                    $adminModel->id
                );
            }
            
            return response()->json([
                'success' => true,
                'data' => $newAdmin
            ], 201);
        } catch (\App\Domain\Exceptions\AuthorizationException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateAdminRequest $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $admin = AdminFactory::createFromModel($adminModel);
            
            $this->authorizeActionUseCase->execute($admin, 'admin-update');
            
            $updatedAdmin = $this->updateAdminUseCase->execute(
                $request->input('id'),
                $request->input('name'),
                $request->input('email'),
                $request->input('password'),
                $request->input('is_active')
            );
            
            return response()->json([
                'success' => true,
                'data' => $updatedAdmin
            ], 200);
        } catch (\App\Domain\Exceptions\AuthorizationException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $admin = AdminFactory::createFromModel($adminModel);
            
            $this->authorizeActionUseCase->execute($admin, 'admin-delete');
            
            $request->validate(['id' => 'required|integer|exists:admins,id']);
            
            $this->deleteAdminUseCase->execute($request->input('id'));
            
            return response()->json([
                'success' => true
            ], 200);
        } catch (\App\Domain\Exceptions\AuthorizationException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
}

