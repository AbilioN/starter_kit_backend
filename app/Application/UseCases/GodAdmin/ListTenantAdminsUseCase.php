<?php

namespace App\Application\UseCases\GodAdmin;

use App\Domain\Services\TenantConnectionSwitcherInterface;
use App\Models\Admin;

/**
 * The tenant's admins, read from that tenant's own database, for the GodAdmin
 * panel to pick who to impersonate.
 *
 * Returns plain arrays rather than Eloquent models on purpose: the caller is a
 * Livewire component, whose public state round-trips through the browser, and
 * an Admin model carries the password hash and remember token with it.
 */
class ListTenantAdminsUseCase
{
    public function __construct(
        private TenantConnectionSwitcherInterface $tenantConnection,
    ) {}

    /**
     * @return array<int, array{id: string, name: string, email: string, is_active: bool, is_tenant_owner: bool}>
     */
    public function execute(string $databaseName): array
    {
        return $this->tenantConnection->run($databaseName, fn () => Admin::query()
            ->orderByDesc('is_tenant_owner')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active', 'is_tenant_owner'])
            ->map(fn (Admin $admin) => [
                'id' => (string) $admin->id,
                'name' => (string) $admin->name,
                'email' => (string) $admin->email,
                'is_active' => (bool) $admin->is_active,
                'is_tenant_owner' => (bool) $admin->is_tenant_owner,
            ])
            ->all());
    }
}
