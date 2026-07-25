<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\UseCases\Admin\GetDashboardMetricsUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GetDashboardMetricsUseCase $getDashboardMetrics,
    ) {}

    public function index(): JsonResponse
    {
        $metrics = $this->getDashboardMetrics->execute();

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }
}
