<?php

namespace App\Models;

use App\Domain\Entities\ChatUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Domain\Entities\Admin as AdminEntity;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Permission;
use App\Models\Role;
use App\Traits\HasAuditLog;

class Admin extends Authenticatable implements ChatUser
{
    use HasUuids, HasApiTokens, HasFactory, Notifiable, HasAuditLog, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'is_super_admin',
        'is_tenant_owner',
        'last_login_at',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_tenant_owner' => 'boolean',
        ];
    }

    public function toEntity(): ChatUser
    {
        return new AdminEntity(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            password: $this->password,
            isActive: $this->is_active,
            isSuperAdmin: $this->is_super_admin,
            isTenantOwner: (bool) $this->is_tenant_owner,
            lastLoginAt: $this->last_login_at,
            createdAt: $this->created_at,
            updatedAt: $this->updated_at
        );
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'admin_roles')
                    ->withPivot(['assigned_at', 'assigned_by']);
    }

    public function permissions()
    {
        return $this->roles()->with('permissions')->get()
                    ->pluck('permissions')->flatten()->unique('id');
    }

    // Implementação da interface ChatUser

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getType(): string
    {
        return 'admin';
    }

    // O método isActive() já existe e é compatível com a interface
}
