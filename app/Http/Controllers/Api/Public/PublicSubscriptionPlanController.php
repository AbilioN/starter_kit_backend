<?php

namespace App\Http\Controllers\Api\Public;

use App\Application\UseCases\Public\GetPublicSubscriptionPlansUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PublicSubscriptionPlanController extends Controller
{
    public function index(GetPublicSubscriptionPlansUseCase $getPublicSubscriptionPlans): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $getPublicSubscriptionPlans->execute()]);
    }

    public function show(string $slug, GetPublicSubscriptionPlansUseCase $getPublicSubscriptionPlans): JsonResponse
    {
        $plan = $getPublicSubscriptionPlans->findBySlug($slug);

        if (! $plan) {
            return response()->json(['success' => false, 'message' => 'Plan not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $plan]);
    }
}
