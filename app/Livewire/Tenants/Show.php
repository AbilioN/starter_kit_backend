<?php

namespace App\Livewire\Tenants;

use App\Application\UseCases\GodAdmin\SuspendTenantUseCase;
use App\Application\UseCases\GodAdmin\UpdateTenantBrandingUseCase;
use App\Domain\Repositories\TenantRepositoryInterface;
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

    public function mount(string $tenantId): void
    {
        $this->tenantId = $tenantId;

        $tenant = app(TenantRepositoryInterface::class)->findById($tenantId);

        if (! $tenant) {
            abort(404);
        }

        $this->themePrimaryColor = $tenant->themePrimaryColor ?? '#4F46E5';
        $this->themeSecondaryColor = $tenant->themeSecondaryColor ?? '#64748B';
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

        return view('livewire.tenants.show', ['tenant' => $tenant])
            ->layout('layouts.god');
    }
}
