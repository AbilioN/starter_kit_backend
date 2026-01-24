<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerification extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'code',
        'expires_at',
        'verified_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }
}
