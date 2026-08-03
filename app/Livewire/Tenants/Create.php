<?php

namespace App\Livewire\Tenants;

use App\Application\UseCases\Tenant\ProvisionTenantUseCase;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use DomainException;
use Livewire\Component;

class Create extends Component
{
    public string $name = '';

    public string $subdomain = '';

    public ?string $planId = null;

    public string $adminEmail = '';

    public string $adminPassword = '';

    public string $error = '';

    public function save(ProvisionTenantUseCase $provisionTenant): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'subdomain' => 'required|string|max:63|regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
            'planId' => 'nullable|uuid',
            'adminEmail' => 'required|email',
            'adminPassword' => 'required|string|min:8',
        ]);

        try {
            $tenant = $provisionTenant->execute(
                name: $this->name,
                subdomain: $this->subdomain,
                subscriptionPlanId: $this->planId,
                createdVia: 'godadmin',
                adminEmail: $this->adminEmail,
                adminPassword: $this->adminPassword,
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
