<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\InfrastructureProvider;
use App\Domain\Entities\Tenant;
use App\Domain\Exceptions\BackupDestinationException;
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

    public function resolveMapsConfig(Tenant $tenant): ?array
    {
        $provider = $this->resolveProvider(
            $tenant,
            tenantProviderId: $tenant->mapsProviderId,
            planProviderId: fn ($plan) => $plan->mapsProviderId,
        );

        return $provider ? ['api_key' => $provider->config['api_key'] ?? null] : null;
    }

    /**
     * Never returns null and never falls back silently — see the interface for
     * why this one type is different.
     *
     * @return array{provider_id: ?string, disk_name: string, config: array}
     */
    public function resolveBackupConfig(?Tenant $tenant): array
    {
        $provider = $tenant === null ? null : $this->resolveProvider(
            $tenant,
            tenantProviderId: $tenant->backupProviderId,
            planProviderId: fn ($plan) => $plan->backupProviderId,
        );

        if ($provider) {
            return [
                'provider_id' => $provider->id,
                // Not 's3'. The disk is built ad hoc per tenant
                // (Storage::build()); naming it after the provider keeps two
                // tenants in the same loop from ever sharing a resolved disk.
                'disk_name' => 'backup_provider_'.$provider->id,
                'config' => $this->mapBackupConfig($provider),
            ];
        }

        $diskName = config('backup.default_disk');
        $config = config("filesystems.disks.{$diskName}");

        if (! is_array($config)) {
            throw new BackupDestinationException(
                "No backup destination: neither a backup provider nor the '{$diskName}' disk is configured."
            );
        }

        $this->assertUsableBackupDisk($config, "the '{$diskName}' disk");

        return ['provider_id' => null, 'disk_name' => $diskName, 'config' => $config];
    }

    public function resolveBackupConfigById(?string $providerId): array
    {
        $provider = $providerId ? $this->providerRepository->findById($providerId) : null;

        // Note: deliberately NOT filtered by is_active, unlike every other
        // resolution here. A provider switched off is a reason to stop writing
        // new backups to it, never a reason to lose the ability to read the
        // ones already there.
        if ($provider) {
            return [
                'provider_id' => $provider->id,
                'disk_name' => 'backup_provider_'.$provider->id,
                'config' => $this->mapBackupConfig($provider),
            ];
        }

        return $this->resolveBackupConfig(null);
    }

    private function mapBackupConfig(InfrastructureProvider $provider): array
    {
        $config = $provider->config;

        $mapped = [
            'driver' => $config['driver'] ?? 's3',
            'key' => $config['key'] ?? null,
            'secret' => $config['secret'] ?? null,
            'region' => $config['region'] ?? null,
            'bucket' => $config['bucket'] ?? null,
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => $config['use_path_style'] ?? false,
            'root' => $config['root'] ?? null,
            // Unlike every other disk in this app, a write failure here must
            // surface. A backup that silently returns false is the exact
            // failure mode this whole feature exists to prevent.
            'throw' => true,
            'report' => false,
        ];

        $this->assertUsableBackupDisk($mapped, "backup provider '{$provider->name}'");

        return $mapped;
    }

    /**
     * Catches the half-configured case, which is worse than the unconfigured
     * one: an S3 destination with no bucket looks configured in the UI and
     * writes nowhere.
     */
    private function assertUsableBackupDisk(array $config, string $label): void
    {
        $driver = $config['driver'] ?? null;

        if ($driver === 's3' && empty($config['bucket'])) {
            throw new BackupDestinationException("No backup destination: {$label} has no bucket.");
        }

        if ($driver === 'local' && empty($config['root'])) {
            throw new BackupDestinationException("No backup destination: {$label} has no root path.");
        }

        if (! in_array($driver, ['s3', 'local'], true)) {
            throw new BackupDestinationException("No backup destination: {$label} has unsupported driver '".($driver ?? 'null')."'.");
        }
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
