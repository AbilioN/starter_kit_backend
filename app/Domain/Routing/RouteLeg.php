<?php

namespace App\Domain\Routing;

/** One hop of the round: from a stop to the next. */
final readonly class RouteLeg
{
    public function __construct(
        public RouteStop $from,
        public RouteStop $to,
        public float $distanceMeters,
        public int $durationSeconds,
    ) {}

    public function toArray(): array
    {
        return [
            'from' => $this->from->id,
            'to' => $this->to->id,
            'distance_meters' => round($this->distanceMeters),
            'duration_seconds' => $this->durationSeconds,
        ];
    }
}
