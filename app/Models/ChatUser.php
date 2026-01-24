<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatUser extends Model
{

    protected $table = 'chat_user';

    protected $fillable = [
        'chat_id',
        'user_id',
        'user_type',
        'joined_at',
        'last_read_at',
        'is_active',
    ];

    /** @use HasFactory<\Database\Factories\ChatUserFactory> */
    use HasFactory;
}
