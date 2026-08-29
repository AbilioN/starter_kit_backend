<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'subject_type', 'subject_id',
        'appointment_type_id', 'appointment_status_id',
        'title', 'description',
        'starts_at', 'ends_at', 'all_day',
        'assigned_admin_id', 'created_by_admin_id',
        'location_address', 'location_postcode', 'location_city',
        'location_lat', 'location_lng',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'location_lat' => 'float',
            'location_lng' => 'float',
            'metadata' => 'array',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AppointmentStatus::class, 'appointment_status_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    /** Whatever this appointment is about — a client, an order, a patient. */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Everything that touches a window, including the things that started
     * before it and are still running.
     *
     * Written as an overlap rather than `starts_at BETWEEN`, because a two-day
     * appointment must appear on its second day too — the case a
     * one-column-per-appointment design has to expand rows in PHP to fake.
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function scopeRoutable(Builder $query): Builder
    {
        return $query->whereNotNull('location_lat')->whereNotNull('location_lng');
    }
}
