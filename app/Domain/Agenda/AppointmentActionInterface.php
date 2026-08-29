<?php

namespace App\Domain\Agenda;

use App\Models\Appointment;

/**
 * One thing a person can do to an appointment from its card.
 *
 * The MADCRM study calls the card menu out as worth stealing: send an SMS, open
 * WhatsApp, export to Google Calendar or Outlook, open the audit trail, start a
 * route. The reason it matters is that a triage screen loses its purpose the
 * moment acting on a row means opening the row.
 *
 * Modelled as a registry of typed actions rather than a list of buttons in a
 * template so that a vertical adds its own — "generate quote", "dispatch
 * technician" — without touching the agenda. Each action decides for itself
 * whether it applies to a given appointment, which is what keeps a card's menu
 * honest instead of showing options that fail when clicked.
 */
interface AppointmentActionInterface
{
    /** Stable machine name; what the client sends back to invoke it. */
    public function key(): string;

    public function label(): string;

    public function icon(): ?string;

    /**
     * The sub-menu this belongs to — 'contact', 'export', 'planning'. Null puts
     * it at the top level. A card with fourteen flat items is unusable; the
     * grouping is what makes a long menu readable.
     */
    public function group(): ?string;

    /** RBAC slug required, or null when anyone who sees the card may do it. */
    public function permission(): ?string;

    /**
     * Whether this action makes sense for THIS appointment. A WhatsApp action
     * with no phone number, or a route action with no coordinates, must not be
     * offered — an action that is present but broken is worse than absent.
     */
    public function isAvailableFor(Appointment $appointment): bool;

    /**
     * How the client should perform it: an external link to open, or an
     * endpoint to call. Built server-side so the client never assembles a
     * provider URL or learns a route it should not know.
     *
     * @return array{kind: string, href?: string, endpoint?: string, method?: string, payload?: array}
     */
    public function describe(Appointment $appointment): array;
}
