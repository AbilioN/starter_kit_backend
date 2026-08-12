<?php

namespace App\Models;

use App\Domain\Entities\GodAdmin as GodAdminEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class GodAdmin extends Authenticatable
{
    use HasUuids, HasFactory, Notifiable;

    protected $connection = 'landlord';

    protected $table = 'godadmins';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            // Encrypted at rest: a landlord database dump must not hand over
            // the seed that generates valid TOTP codes. The recovery codes
            // inside this array are ALSO individually hashed before being
            // written (see GodAdminTwoFactorService::storeRecoveryCodes) —
            // the cast only keeps the surrounding JSON unreadable.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Enablement hangs off `two_factor_confirmed_at`, never off the secret
     * alone: setup writes a secret before the user has proved they can
     * generate a code from it, and an abandoned setup must not lock anyone out.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function toEntity(): GodAdminEntity
    {
        return new GodAdminEntity(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            password: $this->password,
            createdAt: $this->created_at,
            updatedAt: $this->updated_at,
        );
    }
}
