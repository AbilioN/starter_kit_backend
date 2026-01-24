<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\UseCases\Admin\Authorization\CreateRoleUseCase;
use App\Application\UseCases\Admin\Authorization\GetRolesUseCase;
use App\Application\UseCases\Admin\Authorization\AttachPermissionsToRoleUseCase;
use App\Application\UseCases\Admin\Authorization\UpdatePermissionsToRoleUseCase;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\Admin\Authorization\UpdateRoleUseCase;
use App\Application\UseCases\Admin\Authorization\DeleteRoleUseCase;
use App\Application\Services\AdminFactory;
use App\Domain\Exceptions\AuthorizationException;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    
    public function __construct(
        private GetRolesUseCase $getRolesUseCase,
        private CreateRoleUseCase $createRoleUseCase,
        private UpdateRoleUseCase $updateRoleUseCase,
        private DeleteRoleUseCase $deleteRoleUseCase,
        private AttachPermissionsToRoleUseCase $attachPermissionsToRoleUseCase,
        private UpdatePermissionsToRoleUseCase $updatePermissionsToRoleUseCase,
        private AuthorizeActionUseCase $authorizeActionUseCase
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $user = AdminFactory::createFromModel($adminModel);
            $this->authorizeActionUseCase->execute($user, 'role-read');
            $roles = $this->getRolesUseCase->execute();
            $roles = array_map(function ($role) {
                return $role->toDto()->toArray();
            }, $roles);
            return response()->json($roles, 200);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function create(Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $user = AdminFactory::createFromModel($adminModel);
            $this->authorizeActionUseCase->execute($user, 'role-create');
            $name = $request->input('name');
            $description = $request->input('description');
            $permissionsIds = $request->input('permissions') ?? [];
            $role = $this->createRoleUseCase->execute($name, $description);
            if(count($permissionsIds) > 0){
                $this->authorizeActionUseCase->execute($user, 'role-manage');
                $role = $this->attachPermissionsToRoleUseCase->execute($role->getId(), $permissionsIds);
            }
            return response()->json(
                [
                    'success' => true,
                    'data' => [
                        'role' => $role->toDto()->toArray()
                    ]
                ], 201);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $user = AdminFactory::createFromModel($adminModel);
            $this->authorizeActionUseCase->execute($user, 'role-update');
            $id = $request->input('id');
            $name = $request->input('name');
            $description = $request->input('description');
            $role = $this->updateRoleUseCase->execute($id, $name, $description);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'role' => $role->toDto()->toArray()
                ]
            ], 200);
            
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $user = AdminFactory::createFromModel($adminModel);
            $this->authorizeActionUseCase->execute($user, 'role-delete');
            
            $id = $request->input('id');
            $this->deleteRoleUseCase->execute($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ], 200);
            
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updatePermissions(Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $user = AdminFactory::createFromModel($adminModel);
            $this->authorizeActionUseCase->execute($user, 'role-manage');
            $id = $request->input('id');
            $permissionsIds = $request->input('permissions') ?? [];
            $role = $this->updatePermissionsToRoleUseCase->execute($id, $permissionsIds);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'role' => $role->toDto()->toArray()
                ]
            ], 200);
            
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
