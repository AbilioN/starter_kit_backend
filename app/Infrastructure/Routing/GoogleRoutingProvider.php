<?php

namespace App\Infrastructure\Routing;

use App\Domain\Routing\Coordinates;
use App\Domain\Routing\OptimizedRoute;
use App\Domain\Routing\RouteLeg;
use App\Domain\Routing\RouteStop;
use App\Domain\Routing\RoutingProviderInterface;
use RuntimeException;
use Illuminate\Support\Facades\Http;

/**
 * Google Directions with `optimizeWaypoints`, called **from the server**.
 *
 * Server-side deliberately, and it is the one change the MADCRM study insists
 * on: calling from the browser leaves the key defended by nothing but an HTTP
 * referrer allowlist, puts the cache in one person's browser instead of the
 * tenant's, and leaves the usage ledger trusting the client to report itself.
 * A thin proxy fixes all three.
 *
 * The app does not solve the travelling-salesman problem here — it delegates
 * the ordering and spends its own code on framing the question (loop or open
 * path, which subset of stops) and on turning the answer into a roadmap.
 */
class GoogleRoutingProvider implements RoutingProviderInterface
{
    /**
     * Google accepts at most 25 points per Directions request. Two of them are
     * the endpoints, so 23 waypoints — refused with a message rather than
     * silently truncated, because a route missing three stops looks right.
     */
    public const MAX_WAYPOINTS = 23;

    public function __construct(private string $apiKey) {}

    public function name(): string
    {
        return 'google';
    }

    public function isEstimate(): bool
    {
        return false;
    }

    public function optimize(RouteStop $origin, RouteStop $destination, array $waypoints): OptimizedRoute
    {
        if (count($waypoints) > self::MAX_WAYPOINTS) {
            throw new RuntimeException(
                'Too many stops for one route: '.count($waypoints).' waypoints, maximum '.self::MAX_WAYPOINTS.'.'
            );
        }

        $response = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/directions/json', [
            'origin' => $this->point($origin),
            'destination' => $this->point($destination),
            'waypoints' => 'optimize:true|'.implode('|', array_map(
                fn (RouteStop $stop) => $this->point($stop), $waypoints,
            )),
            'mode' => 'driving',
            'key' => $this->apiKey,
        ]);

        $body = $response->json();

        // Google reports key and referrer problems in the body, with HTTP 200.
        // Treating a 200 as success is how a broken key becomes an empty map
        // and an undiagnosable bug report.
        if (($body['status'] ?? null) !== 'OK') {
            throw new RuntimeException(
                'Directions request failed: '.($body['status'] ?? 'unknown')
                .($body['error_message'] ?? '' ? ' — '.$body['error_message'] : '')
            );
        }

        $route = $body['routes'][0];
        $order = $route['waypoint_order'] ?? range(0, count($waypoints) - 1);

        $sequence = [$origin, ...array_map(fn (int $i) => $waypoints[$i], $order), $destination];

        return new OptimizedRoute(
            stops: $sequence,
            legs: $this->legs($sequence, $route['legs'] ?? []),
            provider: $this->name(),
            estimated: false,
        );
    }

    private function point(RouteStop $stop): string
    {
        return $stop->coordinates->lat.','.$stop->coordinates->lng;
    }

    /**
     * @param  array<int, RouteStop>  $sequence
     * @param  array<int, array>      $legs  as returned, already in driving order
     * @return array<int, RouteLeg>
     */
    private function legs(array $sequence, array $legs): array
    {
        $result = [];

        foreach ($legs as $index => $leg) {
            if (! isset($sequence[$index], $sequence[$index + 1])) {
                break;
            }

            $result[] = new RouteLeg(
                from: $sequence[$index],
                to: $sequence[$index + 1],
                distanceMeters: (float) ($leg['distance']['value'] ?? 0),
                durationSeconds: (int) ($leg['duration']['value'] ?? 0),
            );
        }

        return $result;
    }
}
