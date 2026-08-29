<?php

namespace App\Domain\Routing;

interface RoutingProviderInterface
{
    /** Name recorded in the usage ledger and returned with the route. */
    public function name(): string;

    /** Whether its figures are estimates rather than real driving data. */
    public function isEstimate(): bool;

    /**
     * Orders the stops to minimise total travel, from a fixed origin and to a
     * fixed destination.
     *
     * @param  array<int, RouteStop>  $waypoints  the stops between them, in any order
     */
    public function optimize(RouteStop $origin, RouteStop $destination, array $waypoints): OptimizedRoute;
}
