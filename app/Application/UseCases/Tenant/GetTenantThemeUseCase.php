<?php

namespace App\Application\UseCases\Tenant;

use App\Domain\Services\TenantInfrastructureResolverInterface;

class GetTenantThemeUseCase
{
    public function __construct(
        private TenantInfrastructureResolverInterface $infraResolver,
    ) {}

    public function execute(): array
    {
        $tenant = app('currentTenant');

        // Never the secret — this is a public, unauthenticated endpoint.
        // null/null means the tenant has no broadcasting provider override
        // nor a plan default, so the frontend falls back to its own
        // build-time static Pusher config exactly like before this existed.
        $broadcastingConfig = $this->infraResolver->resolveBroadcastingConfig($tenant->toEntity());

        return [
            'name' => $tenant->name,
            'primary_color' => $tenant->theme_primary_color,
            'secondary_color' => $tenant->theme_secondary_color,
            'tertiary_color' => $tenant->theme_tertiary_color,
            'logo_url' => $tenant->logo_path ? asset('storage/'.$tenant->logo_path) : null,
            // small (32) / medium (128) / large (512), keyed by size so a
            // client picks per context instead of scaling one image down —
            // empty for a tenant whose logo predates icon generation, which
            // is why logo_url above stays the fallback rather than being
            // replaced by these.
            'icon_urls' => array_map(
                fn (string $path) => asset('storage/'.$path),
                $tenant->icon_paths ?? [],
            ),
            'pusher_key' => $broadcastingConfig['key'] ?? null,
            'pusher_cluster' => $broadcastingConfig['options']['cluster'] ?? null,
        ];
    }
}
