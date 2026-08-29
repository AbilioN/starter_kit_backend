<?php

namespace Database\Seeders;

use App\Models\AppointmentStatus;
use App\Models\AppointmentType;
use Illuminate\Database\Seeder;

/**
 * The vocabulary a tenant starts with.
 *
 * Types and statuses are seeded rather than hardcoded because every vertical
 * renames them — a clinic's "consultation" is a field-sales "visit" — and a
 * rename must never need a migration. These are defaults to edit, not a fixed
 * list.
 */
class AgendaSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['slug' => 'visit',    'label' => 'Visit',        'color' => '#2563EB', 'icon' => 'map-pin',  'default_duration_minutes' => 90, 'position' => 1],
            ['slug' => 'meeting',  'label' => 'Meeting',      'color' => '#7C3AED', 'icon' => 'users',    'default_duration_minutes' => 60, 'position' => 2],
            // A call-back is a reminder, not an hour of someone's day, so it
            // does not block time and capacity counts can tell them apart.
            ['slug' => 'callback', 'label' => 'Call back',    'color' => '#059669', 'icon' => 'phone',    'default_duration_minutes' => 15, 'position' => 3, 'blocks_time' => false],
            ['slug' => 'task',     'label' => 'Task',         'color' => '#D97706', 'icon' => 'check',    'default_duration_minutes' => 30, 'position' => 4, 'blocks_time' => false],
        ];

        foreach ($types as $type) {
            AppointmentType::updateOrCreate(['slug' => $type['slug']], $type);
        }

        $statuses = [
            ['slug' => 'scheduled', 'label' => 'Scheduled', 'color' => '#6B7280', 'position' => 1],
            ['slug' => 'confirmed', 'label' => 'Confirmed', 'color' => '#059669', 'position' => 2, 'counts_as_confirmed' => true],
            ['slug' => 'done',      'label' => 'Done',      'color' => '#1D4ED8', 'position' => 3, 'counts_as_confirmed' => true, 'is_final' => true],
            ['slug' => 'cancelled', 'label' => 'Cancelled', 'color' => '#DC2626', 'position' => 4, 'is_final' => true],
            ['slug' => 'no_show',   'label' => 'No show',   'color' => '#B91C1C', 'position' => 5, 'is_final' => true],
        ];

        foreach ($statuses as $status) {
            AppointmentStatus::updateOrCreate(['slug' => $status['slug']], $status);
        }

        $this->command?->info('  '.count($types).' appointment types, '.count($statuses).' statuses.');
    }
}
