<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function show(int $branchId): JsonResponse
    {
        $metrics = $this->dashboardService->getBranchMetrics($branchId);

        return response()->json([
            'branch_id' => $branchId,
            'metrics' => $metrics,
        ]);
    }
}
