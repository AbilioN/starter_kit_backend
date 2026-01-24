<?php

namespace App\Application\DTOs\Admin\Authorization;

class PermissionDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $description,
        public readonly bool $is_active,
        public readonly string $resource,
        public readonly string $action,
        public readonly ?string $route = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'resource' => $this->resource,
            'action' => $this->action,
            'route' => $this->route
        ];
    }
}