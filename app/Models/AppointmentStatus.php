<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AppointmentStatus extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug', 'label', 'color', 'counts_as_confirmed', 'is_final', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'counts_as_confirmed' => 'boolean',
            'is_final' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }
}
