<?php

namespace App\Domain\Routing;

/**
 * The answer: the stops in the order to drive them, with per-leg and total
 * figures.
 *
 * The MADCRM study is clear that the deliverable is not a line on a map — it is
 * an ordered, printable roadmap with a distance and a time against every hop.
 * The map is how a person checks the plan; the list is what they drive. So the
 * ordered stops and their legs are the payload, and drawing is the client's
 * problem.
 */
final readonly class OptimizedRoute
{
    /**
     * @param  array<int, RouteStop>  $stops     in driving order
     * @param  array<int, RouteLeg>   $legs      one fewer than the stops
     * @param  string                 $provider  who computed it, so a reader can
     *                                           tell a real drive from an estimate
     */
    public function __construct(
        public array $stops,
        public array $legs,
        public string $provider,
        public bool $estimated,
    ) {}

    public function totalDistanceMeters(): float
    {
        return array_sum(array_map(fn (RouteLeg $leg) => $leg->distanceMeters, $this->legs));
    }

    public function totalDurationSeconds(): int
    {
        return (int) array_sum(array_map(fn (RouteLeg $leg) => $leg->durationSeconds, $this->legs));
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            // Says plainly whether these figures are a drive or a guess. A
            // straight-line estimate presented as a duration is how a driver
            // ends up late and blames the tool.
            'estimated' => $this->estimated,
            'stops' => array_map(fn (RouteStop $stop, int $order) => [
                'order' => $order,
                'id' => $stop->id,
                'label' => $stop->label,
                'address' => $stop->address,
                'lat' => $stop->coordinates->lat,
                'lng' => $stop->coordinates->lng,
            ], $this->stops, array_keys($this->stops)),
            'legs' => array_map(fn (RouteLeg $leg) => $leg->toArray(), $this->legs),
            'total_distance_meters' => round($this->totalDistanceMeters()),
            'total_duration_seconds' => $this->totalDurationSeconds(),
        ];
    }
}
