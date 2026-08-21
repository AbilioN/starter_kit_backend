<?php

namespace App\Livewire\Tenants;

use App\Application\UseCases\GodAdmin\ChangeTenantSubscriptionPlanAsGodAdminUseCase;
use App\Application\UseCases\GodAdmin\ListTenantAdminsUseCase;
use App\Application\UseCases\GodAdmin\StartImpersonationUseCase;
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
use Throwable;

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

    /**
     * Why this support session is being opened. Free text, written into both
     * audit logs and shown to the tenant owner in their notification — the
     * point is that the customer can read the justification, not just the fact.
     */
    public string $impersonationReason = '';

    public string $impersonationError = '';

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

    /**
     * Opens a support session as one of the tenant's admins and hands the
     * browser over to the admin panel carrying the token.
     *
     * Read-only unless $mode is 'write': reproducing a customer's bug almost
     * never requires changing their data, and an operator who alters it by
     * accident is a worse incident than an unreproduced bug. Write access is a
     * separate button, and lands in the audit log as a different thing.
     */
    public function startImpersonation(string $adminId, string $mode, StartImpersonationUseCase $startImpersonation)
    {
        $this->impersonationError = '';

        try {
            $session = $startImpersonation->execute(
                godAdminId: (string) Auth::guard('godadmin')->id(),
                tenantId: $this->tenantId,
                adminId: $adminId,
                mode: $mode,
                reason: trim($this->impersonationReason) ?: null,
            );
        } catch (DomainException $e) {
            $this->impersonationError = $e->getMessage();

            return null;
        }

        $this->impersonationReason = '';

        // The panel is a separate SPA on its own origin, so the handover is a
        // redirect it can read. `tenant` travels in the query string because
        // this dev setup has no wildcard DNS — the panel already resolves
        // tenants the same way.
        $panel = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');

        return redirect()->away($panel.'/impersonate?'.http_build_query([
            'token' => $session['token'],
            'tenant' => $session['subdomain'],
            'expires_at' => $session['expires_at'],
        ]));
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

        // A tenant whose database cannot be reached is exactly when this page
        // is most needed, so an unreachable one degrades to an explanation
        // rather than taking the whole page down with it.
        $tenantAdmins = [];
        $tenantAdminsError = '';

        try {
            $tenantAdmins = app(ListTenantAdminsUseCase::class)->execute($tenant->databaseName);
        } catch (Throwable $e) {
            $tenantAdminsError = 'Could not read this tenant\'s admins: '.$e->getMessage();
        }

        return view('livewire.tenants.show', [
            'tenant' => $tenant,
            'tenantAdmins' => $tenantAdmins,
            'tenantAdminsError' => $tenantAdminsError,
            'plan' => $plan,
            'plans' => $plans,
            'broadcastingProviders' => $providerRepository->findByType('broadcasting'),
            'storageProviders' => $providerRepository->findByType('storage'),
            'aiProviders' => $providerRepository->findByType('ai'),
        ])->layout('layouts.god');
    }
}
