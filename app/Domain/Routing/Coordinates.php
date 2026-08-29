<?php

namespace App\Domain\Routing;

final readonly class Coordinates
{
    public function __construct(public float $lat, public float $lng) {}

    /**
     * Great-circle distance in metres.
     *
     * Straight-line, and therefore always an UNDERESTIMATE of a drive. Good
     * enough to order stops — the relative order of nearby points rarely
     * changes between crow-flight and road — and not good enough to promise a
     * travel time, which is exactly why the local provider reports its
     * durations as estimates and a real routing provider exists behind the
     * same interface.
     */
    public function distanceTo(self $other): float
    {
        $earthRadius = 6_371_000;

        $latFrom = deg2rad($this->lat);
        $latTo = deg2rad($other->lat);
        $deltaLat = deg2rad($other->lat - $this->lat);
        $deltaLng = deg2rad($other->lng - $this->lng);

        $a = sin($deltaLat / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($deltaLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
