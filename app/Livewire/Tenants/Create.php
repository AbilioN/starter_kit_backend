<?php

namespace App\Livewire\Tenants;

use App\Application\UseCases\Tenant\ProvisionTenantUseCase;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use DomainException;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $subdomain = '';

    public ?string $planId = null;

    public string $adminEmail = '';

    public string $adminPassword = '';

    public string $themePrimaryColor = '#4F46E5';

    public string $themeSecondaryColor = '#64748B';

    public $logo = null;

    public string $error = '';

    public function save(ProvisionTenantUseCase $provisionTenant): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'subdomain' => 'required|string|max:63|regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
            'planId' => 'nullable|uuid',
            'adminEmail' => 'required|email',
            'adminPassword' => 'required|string|min:8',
            'themePrimaryColor' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'themeSecondaryColor' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'logo' => 'nullable|image|max:2048',
        ]);

        $logoPath = $this->logo?->store('tenant-logos', 'public');

        try {
            $tenant = $provisionTenant->execute(
                name: $this->name,
                subdomain: $this->subdomain,
                subscriptionPlanId: $this->planId,
                createdVia: 'godadmin',
                adminEmail: $this->adminEmail,
                adminPassword: $this->adminPassword,
                themePrimaryColor: $this->themePrimaryColor,
                themeSecondaryColor: $this->themeSecondaryColor,
                logoPath: $logoPath,
            );
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->redirect('/god/tenants/'.$tenant->id, navigate: false);
    }

    public function render()
    {
        $plans = app(SubscriptionPlanRepositoryInterface::class)->findActive();

        return view('livewire.tenants.create', ['plans' => $plans])
            ->layout('layouts.god');
    }
}
