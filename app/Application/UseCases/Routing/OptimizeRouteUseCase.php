<?php

namespace App\Application\UseCases\Routing;

use App\Domain\Routing\Coordinates;
use App\Domain\Routing\OptimizedRoute;
use App\Domain\Routing\RouteStop;
use App\Infrastructure\Routing\RoutingProviderResolver;
use App\Models\Appointment;
use App\Models\MapsUsageLog;
use App\Models\Tenant;
use DomainException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a set of stops into the order to drive them.
 *
 * Two things beyond calling a provider, and the study is emphatic that a
 * metered third-party feature needs both:
 *
 * **Hard caps at the entry point**, each refused with a message rather than
 * truncated. A route quietly missing three stops looks correct and sends
 * someone out with the wrong plan.
 *
 * **A usage ledger recording the billable quantity.** Maps APIs bill per
 * element, so the row records stops, not calls.
 *
 * (The third mechanism — a cache of computed legs — is the natural next step
 * and is deliberately absent: it needs a stable place id to key on, and keying
 * on an address slug collides across towns with identical street names.)
 */
class OptimizeRouteUseCase
{
    public function __construct(private RoutingProviderResolver $providers) {}

    /**
     * @param  array<int, string>  $appointmentIds  the stops, as appointments
     * @param  string|null  $originId  which of them to start from; the first otherwise
     */
    public function execute(
        array $appointmentIds,
        ?string $originId,
        ?string $destinationId,
        bool $roundTrip,
        ?Tenant $tenant,
        ?string $actorId,
        ?string $actorType,
    ): array {
        $maxStops = (int) config('routing.max_stops');

        if (count($appointmentIds) < 2) {
            throw new DomainException('A route needs at least two stops.');
        }

        if (count($appointmentIds) > $maxStops) {
            throw new DomainException(
                'Too many stops: '.count($appointmentIds).'. The maximum is '.$maxStops.' — narrow the selection first.'
            );
        }

        $stops = $this->stops($appointmentIds);

        if (count($stops) < 2) {
            throw new DomainException(
                'Not enough of the selected appointments have coordinates. Geocode them first.'
            );
        }

        [$origin, $destination, $waypoints] = $this->frame($stops, $originId, $destinationId, $roundTrip);

        $provider = $this->providers->forTenant($tenant);
        $route = $provider->optimize($origin, $destination, $waypoints);

        $this->meter($provider->name(), count($stops), $tenant, $actorId, $actorType);

        return $route->toArray();
    }

    /**
     * Only appointments that actually have coordinates become stops. Silently
     * dropping them would be wrong; the caller is told how many were usable
     * through the count, and the ones without are the geocoder's job.
     *
     * @return array<int, RouteStop>
     */
    private function stops(array $appointmentIds): array
    {
        return Appointment::query()
            ->whereIn('id', $appointmentIds)
            ->routable()
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $a) => new RouteStop(
                id: $a->id,
                label: $a->title,
                coordinates: new Coordinates((float) $a->location_lat, (float) $a->location_lng),
                address: trim(implode(', ', array_filter([
                    $a->location_address, $a->location_postcode, $a->location_city,
                ]))) ?: null,
            ))
            ->values()
            ->all();
    }

    /**
     * Framing the problem is the part the app owns rather than delegates.
     *
     * A round trip is a **closed loop** — origin and destination are the same
     * depot, which is what a day of visits from an office looks like. Two named
     * ends is an **open path**, which is what a delivery run that finishes
     * somewhere else looks like. Getting this wrong produces a technically
     * optimal route to the wrong shape of day.
     *
     * @param  array<int, RouteStop>  $stops
     * @return array{0: RouteStop, 1: RouteStop, 2: array<int, RouteStop>}
     */
    private function frame(array $stops, ?string $originId, ?string $destinationId, bool $roundTrip): array
    {
        $byId = [];
        foreach ($stops as $stop) {
            $byId[$stop->id] = $stop;
        }

        $origin = ($originId !== null && isset($byId[$originId])) ? $byId[$originId] : $stops[0];

        $destination = match (true) {
            $roundTrip => $origin,
            $destinationId !== null && isset($byId[$destinationId]) => $byId[$destinationId],
            // An open path with no stated end: finish at the stop FARTHEST from
            // the origin. Taking "the last one in the list" instead looks
            // reasonable and is not — list order is whatever the query
            // returned, so the same stops could produce a different route on
            // two runs. Ending far away is also what an open run usually
            // means: you set out from the depot and finish at the far end
            // rather than doubling back.
            default => $this->farthestFrom($origin, $stops),
        };

        $waypoints = array_values(array_filter(
            $stops,
            fn (RouteStop $stop) => $stop->id !== $origin->id && $stop->id !== $destination->id,
        ));

        return [$origin, $destination, $waypoints];
    }

    /**
     * @param  array<int, RouteStop>  $stops
     */
    private function farthestFrom(RouteStop $origin, array $stops): RouteStop
    {
        $farthest = null;
        $distance = -1.0;

        foreach ($stops as $stop) {
            if ($stop->id === $origin->id) {
                continue;
            }

            $candidate = $origin->coordinates->distanceTo($stop->coordinates);

            if ($candidate > $distance) {
                $distance = $candidate;
                $farthest = $stop;
            }
        }

        return $farthest ?? $origin;
    }

    /**
     * The ledger row. Never allowed to break the answer: a route that computed
     * fine must not fail because a billing write did.
     */
    private function meter(string $provider, int $quantity, ?Tenant $tenant, ?string $actorId, ?string $actorType): void
    {
        if ($provider === 'local') {
            // Nothing was bought, so there is nothing to bill or throttle.
            return;
        }

        try {
            MapsUsageLog::create([
                'tenant_id' => $tenant?->id,
                'provider' => $provider,
                'operation' => 'route-optimize',
                'quantity' => $quantity,
                'actor_id' => $actorId,
                'actor_type' => $actorType,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to record maps usage', ['error' => $e->getMessage()]);
        }
    }
}
