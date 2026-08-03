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
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
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
