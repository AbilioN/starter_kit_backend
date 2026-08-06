<?php

namespace App\Http\Controllers\Api\Public;

use App\Application\UseCases\Tenant\ProvisionTenantUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTenantSignupRequest;
use DomainException;
use Illuminate\Http\JsonResponse;

class PublicTenantSignupController extends Controller
{
    public function store(PublicTenantSignupRequest $request, ProvisionTenantUseCase $provisionTenant): JsonResponse
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
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $rootDomain = config('app.tenant_domain', 'starterkit.test');

        return response()->json([
            'success' => true,
            'data' => [
                'subdomain' => $tenant->subdomain,
                'redirect_url' => "http://{$tenant->subdomain}.{$rootDomain}",
            ],
        ], 201);
    }
}
