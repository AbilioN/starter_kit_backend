<?php

namespace App\Infrastructure\Routing;

use App\Domain\Routing\RoutingProviderInterface;
use App\Domain\Services\TenantInfrastructureResolverInterface;
use App\Models\Tenant;

/**
 * Which routing provider serves this tenant: its own key, then its plan's,
 * then the local optimiser.
 *
 * The fallback is the point. For `backup`, an unresolved provider must be a
 * recorded failure — silently not backing someone up is the worst thing that
 * feature can do. Here the opposite holds: falling back means the tenant still
 * gets its stops ordered, labelled as estimated, and the feature works in a
 * freshly cloned kit with no account anywhere.
 */
class RoutingProviderResolver
{
    public function __construct(
        private TenantInfrastructureResolverInterface $infrastructure,
    ) {}

    public function forTenant(?Tenant $tenant): RoutingProviderInterface
    {
        if ($tenant === null) {
            return new LocalRoutingProvider();
        }

        $config = $this->infrastructure->resolveMapsConfig($tenant->toEntity());
        $key = $config['api_key'] ?? null;

        return (is_string($key) && $key !== '')
            ? new GoogleRoutingProvider($key)
            : new LocalRoutingProvider();
    }

    /**
     * Whether the call is billable to the platform. A tenant using its own key
     * pays its own bill and is not metered — only calls made on the platform's
     * shared key belong in the ledger.
     */
    public function isMetered(?Tenant $tenant): bool
    {
        return $this->forTenant($tenant)->name() !== 'local';
    }
}
