<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hard caps
    |--------------------------------------------------------------------------
    |
    | Every entry point is capped, and every cap refuses with a message rather
    | than truncating. A route silently missing three stops looks correct, and
    | someone drives it.
    |
    | The numbers come from what the providers and browsers actually tolerate:
    | Google accepts 25 points per Directions request (two endpoints plus 23
    | waypoints), and a map stops being readable — and cheap — well before a
    | couple of hundred pins.
    |
    */

    'max_stops' => (int) env('ROUTING_MAX_STOPS', 25),
    'max_map_points' => (int) env('ROUTING_MAX_MAP_POINTS', 130),

];
