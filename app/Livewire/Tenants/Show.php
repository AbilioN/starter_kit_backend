<?php

namespace App\Livewire\Tenants;

use App\Application\UseCases\GodAdmin\ChangeTenantSubscriptionPlanAsGodAdminUseCase;
use App\Application\UseCases\GodAdmin\SuspendTenantUseCase;
use App\Application\UseCases\GodAdmin\UpdateTenantBrandingUseCase;
use App\Application\UseCases\GodAdmin\UpdateTenantInfrastructureUseCase;
use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public string $tenantId;

    public string $themePrimaryColor = '#4F46E5';

    public string $themeSecondaryColor = '#64748B';

    public $logo = null;

    public string $brandingSaved = '';

    public ?string $selectedPlanId = null;

    public string $planError = '';

    public string $planSaved = '';

    /** '' means "no override — inherit the plan's default (or the global default)". */
    public string $broadcastingProviderId = '';

    public string $storageProviderId = '';

    public string $aiProviderId = '';

    public string $infraSaved = '';

    public function mount(string $tenantId): void
    {
        $this->tenantId = $tenantId;

        $tenant = app(TenantRepositoryInterface::class)->findById($tenantId);

        if (! $tenant) {
            abort(404);
        }

        $this->themePrimaryColor = $tenant->themePrimaryColor ?? '#4F46E5';
        $this->themeSecondaryColor = $tenant->themeSecondaryColor ?? '#64748B';
        $this->selectedPlanId = $tenant->subscriptionPlanId;
        $this->broadcastingProviderId = $tenant->broadcastingProviderId ?? '';
        $this->storageProviderId = $tenant->storageProviderId ?? '';
        $this->aiProviderId = $tenant->aiProviderId ?? '';
    }

    public function saveInfrastructure(UpdateTenantInfrastructureUseCase $updateInfrastructure): void
    {
        $this->infraSaved = '';

        $updateInfrastructure->execute(
            actorId: (string) Auth::guard('godadmin')->id(),
            tenantId: $this->tenantId,
            broadcastingProviderId: $this->broadcastingProviderId ?: null,
            storageProviderId: $this->storageProviderId ?: null,
            aiProviderId: $this->aiProviderId ?: null,
            clearBroadcastingProvider: $this->broadcastingProviderId === '',
            clearStorageProvider: $this->storageProviderId === '',
            clearAiProvider: $this->aiProviderId === '',
        );

        $this->infraSaved = 'Infrastructure overrides updated.';
    }

    public function savePlan(ChangeTenantSubscriptionPlanAsGodAdminUseCase $changePlan): void
    {
        $this->planError = '';
        $this->planSaved = '';

        $this->validate(['selectedPlanId' => 'required|uuid']);

        try {
            $changePlan->execute(
                actorId: (string) Auth::guard('godadmin')->id(),
                tenantId: $this->tenantId,
                subscriptionPlanId: $this->selectedPlanId,
            );
        } catch (DomainException $e) {
            $this->planError = $e->getMessage();

            return;
        }

        $this->planSaved = 'Subscription plan updated.';
    }

    public function toggleStatus(SuspendTenantUseCase $suspendTenant): void
    {
        $tenant = app(TenantRepositoryInterface::class)->findById($this->tenantId);

        if (! $tenant) {
            abort(404);
        }

        $suspendTenant->execute(
            actorId: Auth::guard('godadmin')->id(),
            tenantId: $this->tenantId,
            status: $tenant->isActive() ? 'suspended' : 'active',
        );
    }

    public function saveBranding(UpdateTenantBrandingUseCase $updateBranding): void
    {
        $this->brandingSaved = '';

        $this->validate([
            'themePrimaryColor' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'themeSecondaryColor' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'logo' => 'nullable|image|max:2048',
        ]);

        $logoPath = $this->logo?->store('tenant-logos', 'public');

        $updateBranding->execute(
            actorId: Auth::guard('godadmin')->id(),
            tenantId: $this->tenantId,
            themePrimaryColor: $this->themePrimaryColor,
            themeSecondaryColor: $this->themeSecondaryColor,
            logoPath: $logoPath,
        );

        $this->logo = null;
        $this->brandingSaved = 'Branding updated.';
    }

    public function render()
    {
        $tenant = app(TenantRepositoryInterface::class)->findById($this->tenantId);

        if (! $tenant) {
            abort(404);
        }

        $plan = $tenant->subscriptionPlanId
            ? app(SubscriptionPlanRepositoryInterface::class)->findById($tenant->subscriptionPlanId)
            : null;

        $plans = app(SubscriptionPlanRepositoryInterface::class)->findActive();

        $providerRepository = app(InfrastructureProviderRepositoryInterface::class);

        return view('livewire.tenants.show', [
            'tenant' => $tenant,
            'plan' => $plan,
            'plans' => $plans,
            'broadcastingProviders' => $providerRepository->findByType('broadcasting'),
            'storageProviders' => $providerRepository->findByType('storage'),
            'aiProviders' => $providerRepository->findByType('ai'),
        ])->layout('layouts.god');
    }
}
