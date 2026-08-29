<?php

namespace App\Application\Agenda\Actions;

use App\Domain\Agenda\AppointmentActionInterface;
use App\Models\Appointment;
use App\Models\AppointmentStatus;

/**
 * One-click status change, straight from the card.
 *
 * The MADCRM study is emphatic about this: on a screen whose job is triage,
 * opening a record to change one field is the wrong cost. The action carries
 * the available statuses with it, so the card can render the popup without a
 * second request.
 */
final class ChangeStatusAction implements AppointmentActionInterface
{
    public function key(): string
    {
        return 'status.change';
    }

    public function label(): string
    {
        return 'Change status';
    }

    public function icon(): ?string
    {
        return 'flag';
    }

    public function group(): ?string
    {
        return null;
    }

    public function permission(): ?string
    {
        return 'appointment-update';
    }

    public function isAvailableFor(Appointment $appointment): bool
    {
        return ! ($appointment->status?->is_final ?? false);
    }

    public function describe(Appointment $appointment): array
    {
        return [
            'kind' => 'endpoint',
            'endpoint' => "/api/admin/appointments/{$appointment->id}/status",
            'method' => 'PATCH',
            'field' => 'appointment_status_id',
            'options' => AppointmentStatus::where('is_active', true)
                ->orderBy('position')
                ->get(['id', 'label', 'color'])
                ->all(),
        ];
    }
}
