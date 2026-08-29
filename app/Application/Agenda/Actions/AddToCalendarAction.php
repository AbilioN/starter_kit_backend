<?php

namespace App\Application\Agenda\Actions;

use App\Domain\Agenda\AppointmentActionInterface;
use App\Models\Appointment;

/**
 * A pre-filled "add event" URL for an external calendar.
 *
 * A URL rather than an .ics download, following MADCRM: the link opens the
 * provider's own compose screen already filled in, so the person confirms
 * rather than imports — one click instead of a file, a download and a dialog.
 */
final class AddToCalendarAction implements AppointmentActionInterface
{
    public function __construct(
        private string $provider,
        private string $label,
        private string $template,
    ) {}

    public function key(): string
    {
        return 'calendar.'.$this->provider;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function icon(): ?string
    {
        return 'calendar';
    }

    public function group(): ?string
    {
        return 'export';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function isAvailableFor(Appointment $appointment): bool
    {
        return true;
    }

    public function describe(Appointment $appointment): array
    {
        $replacements = [
            '{title}' => rawurlencode($appointment->title),
            '{details}' => rawurlencode((string) $appointment->description),
            '{location}' => rawurlencode($this->location($appointment)),
            // Both formats the providers between them expect. Built here rather
            // than in the client so a timezone mistake has one place to live.
            '{start_compact}' => $appointment->starts_at->utc()->format('Ymd\THis\Z'),
            '{end_compact}' => $appointment->ends_at->utc()->format('Ymd\THis\Z'),
            '{start_iso}' => rawurlencode($appointment->starts_at->utc()->toIso8601String()),
            '{end_iso}' => rawurlencode($appointment->ends_at->utc()->toIso8601String()),
        ];

        return [
            'kind' => 'link',
            'href' => strtr($this->template, $replacements),
            'target' => '_blank',
        ];
    }

    private function location(Appointment $appointment): string
    {
        return trim(implode(', ', array_filter([
            $appointment->location_address,
            $appointment->location_postcode,
            $appointment->location_city,
        ])));
    }
}
