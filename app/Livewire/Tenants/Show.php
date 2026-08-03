<?php

namespace App\Livewire\Tenants;

use App\Application\UseCases\GodAdmin\SuspendTenantUseCase;
use App\Domain\Repositories\TenantRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public string $tenantId;

    public function mount(string $tenantId): void
    {
        $this->tenantId = $tenantId;
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
