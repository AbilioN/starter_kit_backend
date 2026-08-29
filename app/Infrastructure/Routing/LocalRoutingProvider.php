<?php

namespace App\Infrastructure\Routing;

use App\Domain\Routing\Coordinates;
use App\Domain\Routing\OptimizedRoute;
use App\Domain\Routing\RouteLeg;
use App\Domain\Routing\RouteStop;
use App\Domain\Routing\RoutingProviderInterface;

/**
 * Orders stops with no external service, no API key and no bill.
 *
 * This is the provider a freshly cloned starter kit runs on, and the reason the
 * feature works before anyone has signed up for anything. It is honest about
 * what it is: straight-line distances and an assumed average speed, reported
 * with `estimated: true` so nothing downstream can present a guess as a drive.
 *
 * The algorithm is nearest-neighbour to get an order, then **2-opt** to
 * un-cross it. Nearest-neighbour alone is famously bad in one specific way — it
 * greedily takes the closest next stop and strands the ones it skipped, leaving
 * a long return leg that crosses the whole route. 2-opt repeatedly reverses the
 * segment between two stops whenever doing so shortens the total, which is
 * exactly the operation that removes a crossing. Together they land within a
 * few percent of optimal on the sizes an agenda produces, in microseconds.
 *
 * What this is not: it does not know about roads, one-way streets, traffic or
 * ferries. When those matter, GoogleRoutingProvider sits behind the same
 * interface and nothing above this line changes.
 */
class LocalRoutingProvider implements RoutingProviderInterface
{
    /** Metres per second — roughly 50 km/h, a mixed urban/rural average. */
    private const ASSUMED_SPEED = 13.9;

    /**
     * Straight-line distance under-reads a real drive; roads bend. A detour
     * factor makes the estimate less wrong without pretending to be routing.
     */
    private const DETOUR_FACTOR = 1.3;

    public function name(): string
    {
        return 'local';
    }

    public function isEstimate(): bool
    {
        return true;
    }

    public function optimize(RouteStop $origin, RouteStop $destination, array $waypoints): OptimizedRoute
    {
        $ordered = $this->twoOpt(
            $origin,
            $destination,
            $this->nearestNeighbour($origin, $waypoints),
        );

        $sequence = [$origin, ...$ordered, $destination];

        return new OptimizedRoute(
            stops: $sequence,
            legs: $this->legs($sequence),
            provider: $this->name(),
            estimated: true,
        );
    }

    /**
     * A first order: from where you are, always go to the closest stop you have
     * not visited.
     *
     * @param  array<int, RouteStop>  $waypoints
     * @return array<int, RouteStop>
     */
    private function nearestNeighbour(RouteStop $origin, array $waypoints): array
    {
        $remaining = $waypoints;
        $ordered = [];
        $current = $origin;

        while ($remaining !== []) {
            $bestIndex = null;
            $bestDistance = INF;

            foreach ($remaining as $index => $candidate) {
                $distance = $current->coordinates->distanceTo($candidate->coordinates);

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestIndex = $index;
                }
            }

            $current = $remaining[$bestIndex];
            $ordered[] = $current;
            unset($remaining[$bestIndex]);
        }

        return $ordered;
    }

    /**
     * Un-crosses the route: for every pair of positions, try reversing the
     * segment between them and keep the reversal if the total gets shorter.
     * Repeat until a full pass changes nothing.
     *
     * Bounded by a pass limit as well as by convergence — on a pathological set
     * this could otherwise spend a long time shaving metres, and an agenda's
     * route is planned while someone waits.
     *
     * @param  array<int, RouteStop>  $stops
     * @return array<int, RouteStop>
     */
    private function twoOpt(RouteStop $origin, RouteStop $destination, array $stops): array
    {
        $count = count($stops);

        if ($count < 3) {
            return $stops;
        }

        $best = $stops;
        $bestLength = $this->length($origin, $destination, $best);
        $passes = 0;

        do {
            $improved = false;

            for ($i = 0; $i < $count - 1; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $candidate = $best;
                    $segment = array_slice($candidate, $i, $j - $i + 1);
                    array_splice($candidate, $i, $j - $i + 1, array_reverse($segment));

                    $length = $this->length($origin, $destination, $candidate);

                    if ($length < $bestLength - 0.5) {
                        $best = $candidate;
                        $bestLength = $length;
                        $improved = true;
                    }
                }
            }
        } while ($improved && ++$passes < 20);

        return $best;
    }

    /** @param array<int, RouteStop> $stops */
    private function length(RouteStop $origin, RouteStop $destination, array $stops): float
    {
        $total = 0.0;
        $previous = $origin;

        foreach ([...$stops, $destination] as $stop) {
            $total += $previous->coordinates->distanceTo($stop->coordinates);
            $previous = $stop;
        }

        return $total;
    }

    /**
     * @param  array<int, RouteStop>  $sequence
     * @return array<int, RouteLeg>
     */
    private function legs(array $sequence): array
    {
        $legs = [];

        for ($i = 0; $i < count($sequence) - 1; $i++) {
            $distance = $sequence[$i]->coordinates->distanceTo($sequence[$i + 1]->coordinates)
                * self::DETOUR_FACTOR;

            $legs[] = new RouteLeg(
                from: $sequence[$i],
                to: $sequence[$i + 1],
                distanceMeters: $distance,
                durationSeconds: (int) round($distance / self::ASSUMED_SPEED),
            );
        }

        return $legs;
    }
}
