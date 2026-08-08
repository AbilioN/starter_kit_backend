<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\InfrastructureProvider;
use App\Domain\Entities\Tenant;
use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Services\TenantInfrastructureResolverInterface;

class TenantInfrastructureResolver implements TenantInfrastructureResolverInterface
{
    public function __construct(
        private InfrastructureProviderRepositoryInterface $providerRepository,
        private SubscriptionPlanRepositoryInterface $planRepository,
    ) {}

    public function resolveBroadcastingConfig(Tenant $tenant): ?array
    {
        $provider = $this->resolveProvider(
            $tenant,
            tenantProviderId: $tenant->broadcastingProviderId,
            planProviderId: fn ($plan) => $plan->broadcastingProviderId,
        );

        return $provider ? $this->mapBroadcastingConfig($provider) : null;
    }

    public function resolveStorageConfig(Tenant $tenant): ?array
    {
        $provider = $this->resolveProvider(
            $tenant,
            tenantProviderId: $tenant->storageProviderId,
            planProviderId: fn ($plan) => $plan->storageProviderId,
        );

        return $provider ? $this->mapStorageConfig($provider) : null;
    }

    public function resolveAiConfig(Tenant $tenant): ?array
    {
        $provider = $this->resolveProvider(
            $tenant,
            tenantProviderId: $tenant->aiProviderId,
            planProviderId: fn ($plan) => $plan->aiProviderId,
        );

        return $provider ? $this->mapAiConfig($provider) : null;
    }

    private function resolveProvider(Tenant $tenant, ?string $tenantProviderId, callable $planProviderId): ?InfrastructureProvider
    {
        $providerId = $tenantProviderId;

        if (! $providerId && $tenant->subscriptionPlanId) {
            $plan = $this->planRepository->findById($tenant->subscriptionPlanId);
            $providerId = $plan ? $planProviderId($plan) : null;
        }

        if (! $providerId) {
            return null;
        }

        $provider = $this->providerRepository->findById($providerId);

        return $provider && $provider->isActive ? $provider : null;
    }

    private function mapBroadcastingConfig(InfrastructureProvider $provider): array
    {
        $config = $provider->config;
        $cluster = $config['cluster'] ?? null;

        return [
            'driver' => 'pusher',
            'key' => $config['key'] ?? null,
            'secret' => $config['secret'] ?? null,
            'app_id' => $config['app_id'] ?? null,
            'options' => [
                'cluster' => $cluster,
                'host' => ($config['host'] ?? null) ?: ($cluster ? "api-{$cluster}.pusher.com" : null),
                'port' => 443,
                'scheme' => 'https',
                'encrypted' => true,
                'useTLS' => true,
            ],
            'client_options' => [],
        ];
    }

    private function mapAiConfig(InfrastructureProvider $provider): array
    {
        $config = $provider->config;

        return [
            'api_key' => $config['api_key'] ?? null,
            'model' => $config['model'] ?? null,
            'system_prompt' => $config['system_prompt'] ?? null,
        ];
    }

    private function mapStorageConfig(InfrastructureProvider $provider): array
    {
        $config = $provider->config;

        return [
            'driver' => $config['driver'] ?? 's3',
            'key' => $config['key'] ?? null,
            'secret' => $config['secret'] ?? null,
            'region' => $config['region'] ?? null,
            'bucket' => $config['bucket'] ?? null,
            'url' => $config['url'] ?? null,
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => $config['use_path_style'] ?? false,
            'throw' => false,
            'report' => false,
        ];
    }
}
