<?php

namespace App\Application\Agenda\Actions;

use App\Domain\Agenda\AppointmentActionInterface;
use App\Models\Appointment;

/**
 * Starts a route planned from this stop — the seam between the agenda and the
 * route optimiser.
 *
 * Offered only when this appointment has coordinates, because it is the origin
 * of the route and a route with no origin has nothing to compute.
 */
final class RouteFromHereAction implements AppointmentActionInterface
{
    public function key(): string
    {
        return 'planning.route_from_here';
    }

    public function label(): string
    {
        return 'Plan a route from here';
    }

    public function icon(): ?string
    {
        return 'route';
    }

    public function group(): ?string
    {
        return 'planning';
    }

    public function permission(): ?string
    {
        return 'route-optimize';
    }

    public function isAvailableFor(Appointment $appointment): bool
    {
        return $appointment->location_lat !== null && $appointment->location_lng !== null;
    }

    public function describe(Appointment $appointment): array
    {
        return [
            'kind' => 'endpoint',
            'endpoint' => '/api/admin/routes/optimize',
            'method' => 'POST',
            'payload' => [
                'origin_appointment_id' => $appointment->id,
                'round_trip' => true,
            ],
        ];
    }
}
