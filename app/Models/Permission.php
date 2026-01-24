<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Application\DTOs\Admin\Authorization\PermissionDto;
use App\Domain\Entities\Permission as PermissionEntity;

class Permission extends Model
{
    /** @use HasFactory<\Database\Factories\PermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
        'resource',
        'action',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function toEntity(): PermissionEntity
    {
        return new \App\Domain\Entities\Permission(
            id: $this->id,
            slug: $this->slug,
            name: $this->name,
            description: $this->description,
            is_active: $this->is_active,
            resource: $this->resource,
            action: $this->action
        );
    }

    public function toDto(): PermissionDto
    {
        return new PermissionDto(
            id: $this->id,
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            is_active: $this->is_active,
            resource: $this->resource,
            action: $this->action
        );
    }
}
