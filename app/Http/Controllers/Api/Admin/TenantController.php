<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\UseCases\Tenant\ChangeTenantSubscriptionPlanUseCase;
use App\Application\UseCases\Tenant\GetTenantThemeUseCase;
use App\Application\UseCases\Tenant\UpdateTenantBrandingUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeTenantSubscriptionPlanRequest;
use App\Http\Requests\UpdateTenantBrandingRequest;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function updateSubscriptionPlan(
        ChangeTenantSubscriptionPlanRequest $request,
        ChangeTenantSubscriptionPlanUseCase $changeTenantSubscriptionPlan,
    ): JsonResponse {
        $tenant = app('currentTenant');

        $changeTenantSubscriptionPlan->execute(
            tenantId: $tenant->id,
            adminId: $request->user()->id,
            subscriptionPlanId: $request->validated('subscription_plan_id'),
        );

        return response()->json(['success' => true]);
    }

    public function updateBranding(
        UpdateTenantBrandingRequest $request,
        UpdateTenantBrandingUseCase $updateTenantBranding,
    ): JsonResponse {
        $tenant = app('currentTenant');

        $updated = $updateTenantBranding->execute(
            tenantId: $tenant->id,
            adminId: $request->user()->id,
            themePrimaryColor: $request->validated('theme_primary_color'),
            themeSecondaryColor: $request->validated('theme_secondary_color'),
            logoPath: $request->validated('logo_path'),
        );

        return response()->json(['success' => true, 'data' => [
            'theme_primary_color' => $updated->themePrimaryColor,
            'theme_secondary_color' => $updated->themeSecondaryColor,
            'logo_path' => $updated->logoPath,
        ]]);
    }

    public function theme(GetTenantThemeUseCase $getTenantTheme): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $getTenantTheme->execute()]);
    }
}
