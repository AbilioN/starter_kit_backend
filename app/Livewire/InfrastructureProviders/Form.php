<?php

namespace App\Livewire\InfrastructureProviders;

use App\Application\UseCases\GodAdmin\CreateInfrastructureProviderUseCase;
use App\Application\UseCases\GodAdmin\UpdateInfrastructureProviderUseCase;
use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Form extends Component
{
    public ?string $providerId = null;

    public string $type = 'broadcasting';

    public string $name = '';

    public bool $isActive = true;

    // Shared across both types — "key"/"secret" mean the same credential
    // concept for a Pusher app and an S3-compatible bucket.
    public string $configKey = '';

    public string $configSecret = '';

    // broadcasting only
    public string $configAppId = '';

    public string $configCluster = '';

    public string $configHost = '';

    // storage only
    public string $configRegion = '';

    public string $configBucket = '';

    public string $configEndpoint = '';

    public bool $configUsePathStyle = false;

    public string $configUrl = '';

    private InfrastructureProviderRepositoryInterface $providerRepository;

    // See SubscriptionPlans/Form.php's boot() for why this isn't __construct().
    public function boot(InfrastructureProviderRepositoryInterface $providerRepository): void
    {
        $this->providerRepository = $providerRepository;
    }

    public function mount(?string $providerId = null): void
    {
        if (! $providerId) {
            return;
        }

        $provider = $this->providerRepository->findById($providerId);

        if (! $provider) {
            abort(404);
        }

        $this->providerId = $provider->id;
        $this->type = $provider->type;
        $this->name = $provider->name;
        $this->isActive = $provider->isActive;

        $config = $provider->config;
        $this->configKey = $config['key'] ?? '';
        $this->configSecret = $config['secret'] ?? '';
        $this->configAppId = $config['app_id'] ?? '';
        $this->configCluster = $config['cluster'] ?? '';
        $this->configHost = $config['host'] ?? '';
        $this->configRegion = $config['region'] ?? '';
        $this->configBucket = $config['bucket'] ?? '';
        $this->configEndpoint = $config['endpoint'] ?? '';
        $this->configUsePathStyle = (bool) ($config['use_path_style'] ?? false);
        $this->configUrl = $config['url'] ?? '';
    }

    public function save(
        CreateInfrastructureProviderUseCase $createProvider,
        UpdateInfrastructureProviderUseCase $updateProvider,
    ): void {
        $this->validate([
            'type' => 'required|in:broadcasting,storage',
            'name' => 'required|string|max:255',
            'configKey' => 'required|string',
            'configSecret' => 'required|string',
            'configAppId' => 'required_if:type,broadcasting|string',
            'configCluster' => 'required_if:type,broadcasting|string',
            'configBucket' => 'required_if:type,storage|string',
        ]);

        $config = $this->type === 'broadcasting'
            ? [
                'key' => $this->configKey,
                'secret' => $this->configSecret,
                'app_id' => $this->configAppId,
                'cluster' => $this->configCluster,
                'host' => $this->configHost ?: null,
            ]
            : [
                'key' => $this->configKey,
                'secret' => $this->configSecret,
                'region' => $this->configRegion ?: null,
                'bucket' => $this->configBucket,
                'endpoint' => $this->configEndpoint ?: null,
                'use_path_style' => $this->configUsePathStyle,
                'url' => $this->configUrl ?: null,
            ];

        $actorId = (string) Auth::guard('godadmin')->id();

        if ($this->providerId) {
            $updateProvider->execute(
                actorId: $actorId,
                providerId: $this->providerId,
                name: $this->name,
                config: $config,
                isActive: $this->isActive,
            );
        } else {
            $createProvider->execute(
                actorId: $actorId,
                type: $this->type,
                name: $this->name,
                config: $config,
                isActive: $this->isActive,
            );
        }

        $this->redirect('/god/infrastructure-providers', navigate: false);
    }

    public function render()
    {
        return view('livewire.infrastructure-providers.form')->layout('layouts.god');
    }
}
