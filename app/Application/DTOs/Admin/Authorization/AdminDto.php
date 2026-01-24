<?php

namespace App\Application\DTOs\Admin\Authorization;

class AdminDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly bool $is_active,
        public readonly bool $is_super_admin,
        public readonly ?string $last_login_at = null,
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'is_super_admin' => $this->is_super_admin,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}