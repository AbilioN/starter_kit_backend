<?php

namespace Tests\Feature\Agenda;

use App\Domain\Routing\Coordinates;
use App\Domain\Routing\RouteStop;
use App\Infrastructure\Routing\LocalRoutingProvider;
use Tests\TestCase;

/**
 * The local optimiser, which is what a freshly cloned kit routes on.
 *
 * These are unit tests on purpose: the value of this provider is that it needs
 * no key, no network and no tenant, and the tests should be able to say so by
 * not needing any of them either.
 */
class RouteOptimizationTest extends TestCase
{
    private function stop(string $id, float $lat, float $lng): RouteStop
    {
        return new RouteStop($id, "Stop {$id}", new Coordinates($lat, $lng));
    }

    public function test_it_orders_a_deliberately_scrambled_line_of_stops(): void
    {
        // Five points on a straight line, handed over shuffled. The shortest
        // route through them is the line itself, so the correct answer is
        // knowable rather than merely plausible — which is what makes this a
        // test and not a demonstration.
        $origin = $this->stop('a', 0.0, 0.0);
        $waypoints = [
            $this->stop('d', 0.0, 0.3),
            $this->stop('b', 0.0, 0.1),
            $this->stop('e', 0.0, 0.4),
            $this->stop('c', 0.0, 0.2),
        ];

        $route = (new LocalRoutingProvider())->optimize($origin, $this->stop('f', 0.0, 0.5), $waypoints);

        $this->assertSame(
            ['a', 'b', 'c', 'd', 'e', 'f'],
            array_map(fn (RouteStop $s) => $s->id, $route->stops),
        );
    }

    public function test_two_opt_removes_a_crossing_nearest_neighbour_leaves_behind(): void
    {
        // A square. Nearest-neighbour from a corner is fine here, but the
        // guarantee worth having is that the result never crosses itself: for
        // four corners the optimal tour is the perimeter, and any crossing
        // route is strictly longer.
        $origin = $this->stop('nw', 1.0, 0.0);
        $waypoints = [
            $this->stop('se', 0.0, 1.0),
            $this->stop('ne', 1.0, 1.0),
            $this->stop('sw', 0.0, 0.0),
        ];

        $route = (new LocalRoutingProvider())->optimize($origin, $origin, $waypoints);
        $order = array_map(fn (RouteStop $s) => $s->id, $route->stops);

        // Perimeter in either direction; both are optimal.
        $this->assertContains($order, [
            ['nw', 'ne', 'se', 'sw', 'nw'],
            ['nw', 'sw', 'se', 'ne', 'nw'],
        ]);
    }

    public function test_a_round_trip_returns_to_where_it_started(): void
    {
        $depot = $this->stop('depot', 0.0, 0.0);

        $route = (new LocalRoutingProvider())->optimize($depot, $depot, [
            $this->stop('x', 0.1, 0.1),
            $this->stop('y', 0.2, 0.0),
        ]);

        $this->assertSame('depot', $route->stops[0]->id);
        $this->assertSame('depot', $route->stops[count($route->stops) - 1]->id);
        $this->assertCount(3, $route->legs, 'A four-stop loop has three legs.');
    }

    public function test_it_reports_its_figures_as_estimates(): void
    {
        // The honesty that lets a straight-line optimiser ship: a duration
        // presented as fact is how a driver ends up late and blames the tool.
        $route = (new LocalRoutingProvider())->optimize(
            $this->stop('a', 0.0, 0.0),
            $this->stop('b', 0.0, 0.1),
            [$this->stop('c', 0.0, 0.05)],
        );

        $this->assertTrue($route->estimated);
        $this->assertSame('local', $route->provider);
        $this->assertGreaterThan(0, $route->totalDistanceMeters());
        $this->assertGreaterThan(0, $route->totalDurationSeconds());
    }

    public function test_distance_between_two_known_points_is_right(): void
    {
        // Paris to Lyon is about 392 km great-circle. A haversine that is
        // subtly wrong still produces plausible-looking orderings, so it needs
        // pinning to a number somebody can check.
        $paris = new Coordinates(48.8566, 2.3522);
        $lyon = new Coordinates(45.7640, 4.8357);

        $this->assertEqualsWithDelta(392_000, $paris->distanceTo($lyon), 5_000);
    }
}
