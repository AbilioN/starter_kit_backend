<?php

namespace App\Domain\Entities;

use App\Application\DTOs\Admin\Authorization\RoleDto;
use Illuminate\Database\Eloquent\Model;

class Role
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly bool $is_active,
        public readonly array $permissions = [],
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null,    
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }

    

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function canAccess(string $resource, string $action): bool
    {
        return $this->hasPermission(new Permission($resource, $action));
    }

    public function toDto(): RoleDto
    {

        return new RoleDto(
            id: $this->id,
            slug: $this->slug,
            name: $this->name,
            description: $this->description,
            is_active: $this->is_active,
            permissions: array_map(fn($permission) => $permission->toDto()->toArray(), $this->permissions),
            created_at: $this->created_at,
            updated_at: $this->updated_at,
        );
    }
}
