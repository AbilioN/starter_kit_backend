<?php

namespace App\Domain\CustomFields\Hosts;

use App\Domain\CustomFields\CustomFieldHostInterface;
use App\Domain\CustomFields\HostCeilings;
use App\Models\Appointment;

/**
 * Appointments — the first host, and the one the feature was really built for.
 *
 * The appointments migration says the starter kit has no "client" because
 * "every vertical brings its own noun", and gives the table a nullable morph
 * to point at whatever that noun turns out to be. Custom fields are the same
 * move one level down: the column's existence and shape are ours, what it
 * MEANS is the tenant's, and no code path asks.
 *
 * The agenda study already describes the payoff on the other product's card —
 * a "tenant-specific supplements list (extra fields a vertical adds — surface
 * areas, product strips…)". This is that list.
 */
final class AppointmentsHost implements CustomFieldHostInterface
{
    public function key(): string
    {
        return 'appointments';
    }

    public function table(): string
    {
        return 'appointments';
    }

    public function modelClass(): string
    {
        return Appointment::class;
    }

    public function writePermission(): string
    {
        return 'appointment-update';
    }

    public function featureFlag(): string
    {
        return 'features.custom_fields_appointments';
    }

    public function slots(): array
    {
        return [
            'card.badges' => 'Chips along the bottom of the agenda card',
            'card.subtitle' => 'Beside the title, on the card',
        ];
    }

    public function sections(): array
    {
        return [
            'general' => 'General',
            'location' => 'Location',
            'notes' => 'Notes',
        ];
    }

    public function reservedColumns(): array
    {
        // Everything the code branches on. The reconciler already refuses any
        // name that is not cf_<digits>, so this list can never be the only
        // thing standing between a tenant and a dropped starts_at — it is
        // here so the frozen half of this table is written down somewhere a
        // reviewer reads, next to the reasons.
        return [
            'id',
            // The diary window, and the only compound index the table has.
            'starts_at', 'ends_at', 'all_day',
            // BuildAgendaUseCase::counts() branches on the status's
            // counts_as_confirmed to produce the sub-count the agenda exists for.
            'appointment_type_id', 'appointment_status_id',
            'assigned_admin_id', 'created_by_admin_id',
            // AddToCalendarAction substitutes the title into a third-party URL.
            'title', 'description',
            // scopeRoutable() and RouteFromHereAction read these.
            'location_address', 'location_postcode', 'location_city',
            'location_lat', 'location_lng',
            // The seam this whole feature complements.
            'subject_type', 'subject_id',
            // Superseded by custom fields, kept because dropping it is a
            // rebuild for no gain.
            'metadata',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public function ceilings(): HostCeilings
    {
        // The table starts with 5 secondary indexes and 21 columns, so the
        // defaults already account for a real table rather than an empty one.
        return new HostCeilings;
    }
}
