<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MapsUsageLog extends Model
{
    use HasUuids;

    protected $connection = 'landlord';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'provider', 'operation', 'quantity',
        'actor_id', 'actor_type', 'created_at',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'created_at' => 'datetime'];
    }
}
