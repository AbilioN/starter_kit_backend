<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Domain\Entities\User as UserEntity;
use App\Domain\Entities\ChatUser;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Concerns\HasTenantFields;
use App\Traits\HasAuditLog;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    // HasTenantFields hides every cf_* column from serialisation. It matters
    // most HERE: User uses HasAuditLog, which strips $hidden before writing
    // oldValues/newValues into audit_logs — a table that is immutable by
    // cross-cutting decision. Custom fields are exactly where a tenant puts a
    // national ID or a medical note, and without this they would be copied
    // into a table nobody can ever clean.
    use HasApiTokens, HasAuditLog, HasFactory, HasTenantFields, HasUuids, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'locale',
        'name',
        'email',
        'password',
        'email_verified_at',
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
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function emailVerifications(): HasMany
    {
        return $this->hasMany(EmailVerification::class);
    }

    public function isEmailVerified(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): void
    {
        $this->update(['email_verified_at' => now()]);
    }

    public function toEntity(): ChatUser
    {
        return new UserEntity(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            password: $this->password,
            emailVerifiedAt: $this->email_verified_at
        );
    }
}
