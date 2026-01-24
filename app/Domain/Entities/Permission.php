<?php

namespace App\Domain\Entities;

use App\Application\DTOs\Admin\Authorization\PermissionDto;

class Permission
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly bool $is_active,
        public readonly string $resource,
        public readonly string $action,
        public readonly ?string $route = null
    ) {}


    public function getId(): int
    {
        return $this->id;
    }

    public function toDto(): PermissionDto
    {
        return new PermissionDto(
            id: $this->id,
            slug: $this->slug,
            name: $this->name,
            description: $this->description,
            is_active: $this->is_active,
            resource: $this->resource,
            action: $this->action,
            route: $this->route
        );
    }
}  