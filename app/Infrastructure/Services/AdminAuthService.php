<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\Admin;
use App\Domain\Services\AdminAuthServiceInterface;
use App\Domain\Repositories\AdminRepositoryInterface;
use App\Domain\Exceptions\AuthenticationException;
use App\Models\Admin as AdminModel;

class AdminAuthService implements AdminAuthServiceInterface
{
    public function __construct(
        private AdminRepositoryInterface $adminRepository
    ) {}

    public function authenticate(string $email, string $password): ?Admin
    {
        $admin = $this->adminRepository->findByEmail($email);

        if (!$admin || !$admin->validatePassword($password)) {
            throw new AuthenticationException();
        }

        if (!$admin->isActive()) {
            throw new AuthenticationException('Admin account is not active');
        }

        // Atualizar último login
        $this->adminRepository->updateLastLogin($admin->id);

        return $admin;
    }

    public function generateToken(Admin $admin): string
    {
        // Buscar o modelo Eloquent para usar Sanctum
        $adminModel = AdminModel::find($admin->id);
        
        if (!$adminModel) {
            throw new AuthenticationException('Admin model not found');
        }

        // Gerar token usando Sanctum
        return $adminModel->createToken('admin-api')->plainTextToken;
    }

    public function getAdminPermissions(Admin $admin): array
    {
        // Buscar o modelo Eloquent para acessar as relações
        $adminModel = AdminModel::find($admin->getId());
        
        if (!$adminModel) {
            return [];
        }

        // Obter todas as permissões do admin através das roles
        $permissions = $adminModel->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id')
            ->map(function ($permission) {
                return $permission->toDto()->toArray();
            })
            ->values()
            ->toArray();

        return $permissions;
    }

    public function getAdminRolesWithPermissions(Admin $admin): array
    {
        // Buscar o modelo Eloquent para acessar as relações
        $adminModel = AdminModel::find($admin->getId());
        
        if (!$adminModel) {
            return [];
        }

        // Obter todas as roles do admin com suas permissions
        $roles = $adminModel->roles()
            ->with('permissions')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'permissions' => $role->permissions->map(function ($permission) {
                        return $permission->toDto()->toArray();
                    })->toArray()
                ];
            })
            ->toArray();

        return $roles;
    }
} 