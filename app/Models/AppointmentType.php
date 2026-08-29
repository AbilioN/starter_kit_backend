<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AppointmentType extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug', 'label', 'color', 'icon', 'blocks_time',
        'default_duration_minutes', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'blocks_time' => 'boolean',
            'is_active' => 'boolean',
            'default_duration_minutes' => 'integer',
            'position' => 'integer',
        ];
    }
}
