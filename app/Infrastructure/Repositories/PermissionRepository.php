<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Permission;
use App\Domain\Repositories\PermissionRepositoryInterface;
use App\Models\Permission as PermissionModel;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function findByIds(array $ids): array
    {
        $permissions = PermissionModel::whereIn('id', $ids)->get();
        $permissionsEntities = [];
        $permissions->map(function ($permission) use (&$permissionsEntities) {
            $permissionsEntities[] = $permission->toEntity();
        });
        return $permissionsEntities;
    }

    public function findAll(): array
    {
        return PermissionModel::all()->map(function ($permission) {
            return $permission->toEntity();
        })->toArray();
    }

    public function create(string $name, string $description, string $resource, string $action): Permission
    {
        $permission = PermissionModel::create([
            'name' => $name,
            'description' => $description,
            'resource' => $resource,
            'action' => $action,
        ]);

        return $permission->toEntity();
    }

    public function update(int $id, string $name, string $description, string $resource, string $action): Permission
    {
        $permission = PermissionModel::findOrFail($id);
        $permission->update([
            'name' => $name,
            'description' => $description,
            'resource' => $resource,
            'action' => $action,
        ]);

        return $permission->toEntity();
    }

    public function delete(int $id): void
    {
        PermissionModel::where('id', $id)->delete();
    }

    public function attachRoles(int $id, array $roles): void
    {
        $permission = PermissionModel::findOrFail($id);
        $roleIds = array_map(function ($role) {
            return $role->getId();
        }, $roles);
        $permission->roles()->sync($roleIds);
    }

    public function detachRoles(int $id, array $roles): void
    {
        $permission = PermissionModel::findOrFail($id);
        $roleIds = array_map(function ($role) {
            return $role->getId();
        }, $roles);
        $permission->roles()->detach($roleIds);
    }
}
