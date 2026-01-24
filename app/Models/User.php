<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Domain\Entities\User as UserEntity;
use App\Domain\Entities\ChatUser;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
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
