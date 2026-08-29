<?php

namespace App\Domain\Routing;

final readonly class RouteStop
{
    public function __construct(
        public string $id,
        public string $label,
        public Coordinates $coordinates,
        public ?string $address = null,
    ) {}
}
