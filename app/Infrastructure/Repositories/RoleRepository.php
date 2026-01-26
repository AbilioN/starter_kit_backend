<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Role;
use App\Domain\Repositories\RoleRepositoryInterface;
use App\Models\Role as RoleModel;
use Illuminate\Support\Str;

class RoleRepository implements RoleRepositoryInterface
{
    public function findById(int $id): ?Role
    {

        
        $role = RoleModel::find($id);
        if (!$role) {
            return null;
        }
        return $role->toEntity();
    }

    public function findBySlug(string $slug): ?Role
    {
        $role = RoleModel::where('slug', $slug)->first();
        if (!$role) {
            return null;
        }
        return new Role($role->id, $role->slug, $role->name, $role->description, $role->is_active, $role->permissions);
    }

    public function findByName(string $name): ?Role
    {
        $role = RoleModel::where('name', $name)->first();
        if (!$role) {
            return null;
        }
        return new Role($role->id, $role->slug, $role->name, $role->description, $role->is_active, $role->permissions);
    }

    public function findAll(): array
    {
        return RoleModel::all()->map(function ($role) {
            return $role->toEntity();
        })->toArray();
    }
    
    public function create(string $name, string $description): Role
    {

        $slug = Str::slug($name);
        $role = RoleModel::create([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'is_active' => true,
        ]);
        return $role->toEntity();
    }

    public function update(int $id, string $slug, string $name, string $description): Role
    {
        // Usar findOrFail()->update() para disparar eventos do Eloquent (audit log)
        $role = RoleModel::findOrFail($id);
        $role->update([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
        ]);
        
        return $role->fresh()->toEntity();
    }

    public function delete(int $id): void
    {
        // Usar findOrFail()->delete() para disparar eventos do Eloquent (audit log)
        RoleModel::findOrFail($id)->delete();
    }

    public function updatePermissions(int $roleId, array $permissionIds): Role
    {
        $role = RoleModel::findOrFail($roleId);
        // sync() substitui todas as permissions (remove as antigas e adiciona as novas)
        $role->permissions()->sync($permissionIds);
        return $role->refresh()->toEntity();
    }

    // Manter o método antigo para compatibilidade
    public function attachPermissions(int $roleId, array $permissionIds): Role
    {
        return $this->updatePermissions($roleId, $permissionIds);
    }

}