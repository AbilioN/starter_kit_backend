<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\Routing\OptimizeRouteUseCase;
use App\Helpers\Settings;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Route optimisation, called from the server.
 *
 * Server-side is the point: it keeps the provider key out of the browser, lets
 * the usage ledger record what was actually spent instead of trusting the
 * client to report itself, and puts any future cache where every user of the
 * tenant shares it rather than one person's browser.
 */
class RouteController extends Controller
{
    public function __construct(
        private OptimizeRouteUseCase $optimizeRoute,
        private AuthorizeActionUseCase $authorize,
    ) {}

    public function optimize(Request $request): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'route-optimize');

        // The commercial gate, distinct from the RBAC one above. `route-optimize`
        // answers "may this person plan rounds"; this answers "did this
        // workspace buy the feature at all". Both are needed: MADCRM excludes
        // sales reps from routing on a tenant that pays for it, and that stays
        // expressible only while the two are separate.
        if (! Settings::isEnabled('features.route_optimization')) {
            return response()->json([
                'success' => false,
                'message' => 'Route optimisation is not included in this workspace\'s plan.',
                'error' => 'feature_disabled',
                'feature' => 'route_optimization',
            ], 403);
        }

        $data = $request->validate([
            'appointment_ids' => ['required', 'array', 'min:2'],
            'appointment_ids.*' => ['string'],
            'origin_appointment_id' => ['sometimes', 'nullable', 'string'],
            'destination_appointment_id' => ['sometimes', 'nullable', 'string'],
            'round_trip' => ['sometimes', 'boolean'],
        ]);

        try {
            $route = $this->optimizeRoute->execute(
                appointmentIds: $data['appointment_ids'],
                originId: $data['origin_appointment_id'] ?? null,
                destinationId: $data['destination_appointment_id'] ?? null,
                roundTrip: (bool) ($data['round_trip'] ?? false),
                tenant: app()->bound('currentTenant') ? app('currentTenant') : null,
                actorId: $request->user()->id,
                actorType: 'admin',
            );
        } catch (DomainException|RuntimeException $e) {
            // Caps and provider failures are answers, not crashes: the message
            // tells the person what to change (usually "select fewer stops").
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $route]);
    }
}
