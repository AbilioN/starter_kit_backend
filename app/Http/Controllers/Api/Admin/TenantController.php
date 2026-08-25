<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\UseCases\Tenant\ChangeTenantSubscriptionPlanUseCase;
use App\Application\UseCases\Tenant\GetTenantSubscriptionHistoryUseCase;
use App\Application\UseCases\Tenant\GetTenantThemeUseCase;
use App\Application\UseCases\Tenant\UpdateTenantBrandingUseCase;
use App\Domain\Services\IconResizingServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeTenantSubscriptionPlanRequest;
use App\Http\Requests\UpdateTenantBrandingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantController extends Controller
{
    public function subscriptionHistory(
        Request $request,
        GetTenantSubscriptionHistoryUseCase $getSubscriptionHistory,
    ): JsonResponse {
        // O tenant vem SÓ do container (resolvido por IdentifyTenant a partir do
        // subdomínio). Nunca de um parâmetro: esta é uma leitura de tabela do
        // landlord a partir de rota tenant-facing — o único ponto onde vazamento
        // entre tenants seria possível.
        $tenant = app('currentTenant');

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $page = max((int) $request->query('page', 1), 1);

        return response()->json($getSubscriptionHistory->execute($tenant->id, $page, $perPage));
    }

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
        IconResizingServiceInterface $iconResizingService,
    ): JsonResponse {
        $tenant = app('currentTenant');

        // A new upload produces BOTH: the original is kept as logo_path (the
        // only artwork a tenant can hand back for re-processing, and what a
        // wide header still wants), while icon_paths gets the square 32/128/512
        // variants the sidebar, avatars and favicons actually render — the same
        // pipeline subscription plan icons already go through. Sending
        // logo_path alone (an already-hosted path) deliberately leaves the
        // existing icons in place: there is no source file to resize.
        $iconPaths = null;

        if ($request->hasFile('logo')) {
            $logoPath = Storage::disk('public')->putFile('tenant-logos', $request->file('logo'));
            $iconPaths = $iconResizingService->generateSizes($request->file('logo'), 'tenant-icons');
        } else {
            $logoPath = $request->validated('logo_path');
        }

        $updated = $updateTenantBranding->execute(
            tenantId: $tenant->id,
            adminId: $request->user()->id,
            themePrimaryColor: $request->validated('theme_primary_color'),
            themeSecondaryColor: $request->validated('theme_secondary_color'),
            themeTertiaryColor: $request->validated('theme_tertiary_color'),
            logoPath: $logoPath,
            iconPaths: $iconPaths,
        );

        return response()->json(['success' => true, 'data' => [
            'theme_primary_color' => $updated->themePrimaryColor,
            'theme_secondary_color' => $updated->themeSecondaryColor,
            'theme_tertiary_color' => $updated->themeTertiaryColor,
            'logo_path' => $updated->logoPath,
            'logo_url' => $updated->logoPath ? asset('storage/'.$updated->logoPath) : null,
            'icon_urls' => array_map(
                fn (string $path) => asset('storage/'.$path),
                $updated->iconPaths,
            ),
        ]]);
    }

    public function theme(GetTenantThemeUseCase $getTenantTheme): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $getTenantTheme->execute()]);
    }
}
