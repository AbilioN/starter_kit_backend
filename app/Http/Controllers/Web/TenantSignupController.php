<?php

namespace App\Http\Controllers\Web;

use App\Application\UseCases\Tenant\ProvisionTenantUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\TenantSignupRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;

class TenantSignupController extends Controller
{
    public function __invoke(TenantSignupRequest $request, ProvisionTenantUseCase $provisionTenant): RedirectResponse
    {
        try {
            $tenant = $provisionTenant->execute(
                name: $request->string('name')->toString(),
                subdomain: $request->string('subdomain')->toString(),
                subscriptionPlanId: $request->input('plan_id'),
                createdVia: 'self_service',
                adminEmail: $request->string('admin_email')->toString(),
                adminPassword: $request->string('admin_password')->toString(),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['subdomain' => $e->getMessage()])->withInput();
        }

        return redirect()->away("http://{$tenant->subdomain}.".config('app.tenant_domain', 'starterkit.test'));
    }
}
