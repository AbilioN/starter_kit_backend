<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Welcome to Admin Dashboard',
            'data' => [
                'total_users' => 0,
                'total_admins' => 1,
                'system_status' => 'active'
            ]
        ]);
    }
}
