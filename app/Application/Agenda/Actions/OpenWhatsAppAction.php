<?php

namespace App\Application\Agenda\Actions;

use App\Domain\Agenda\AppointmentActionInterface;
use App\Models\Appointment;

/**
 * Opens WhatsApp for the phone number carried on the appointment.
 *
 * Present only when there IS a number: an action that appears and then fails is
 * worse than one that is absent, which is why availability is per-appointment
 * rather than per-tenant.
 */
final class OpenWhatsAppAction implements AppointmentActionInterface
{
    public function key(): string
    {
        return 'contact.whatsapp';
    }

    public function label(): string
    {
        return 'WhatsApp';
    }

    public function icon(): ?string
    {
        return 'whatsapp';
    }

    public function group(): ?string
    {
        return 'contact';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function isAvailableFor(Appointment $appointment): bool
    {
        return $this->phone($appointment) !== null;
    }

    public function describe(Appointment $appointment): array
    {
        return [
            'kind' => 'link',
            'href' => 'https://wa.me/'.$this->phone($appointment),
            'target' => '_blank',
        ];
    }

    /** Digits only — wa.me rejects anything else, including the leading +. */
    private function phone(Appointment $appointment): ?string
    {
        $raw = $appointment->metadata['phone'] ?? null;

        if (! is_string($raw)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        return ($digits === null || $digits === '') ? null : $digits;
    }
}
