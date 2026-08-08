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
            'logo_url' => $tenant->logo_path ? asset('storage/'.$tenant->logo_path) : null,
            'pusher_key' => $broadcastingConfig['key'] ?? null,
            'pusher_cluster' => $broadcastingConfig['options']['cluster'] ?? null,
        ];
    }
}
