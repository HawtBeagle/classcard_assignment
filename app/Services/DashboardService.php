<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Models\BranchSession;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardService
{
    public function getBranchMetrics(int $branchId): array
    {
        $cacheKey = "dashboard:branch:$branchId";

        return Cache::remember($cacheKey, 300, function () use ($branchId) {
            $startOfMonth = Carbon::now()->startOfMonth();

            $totalRevenue = Invoice::where('branch_id', $branchId)
                ->where('status', 'paid')
                ->where('created_at', '>=', $startOfMonth)
                ->sum('total');

            $totalUnpaid = Invoice::where('branch_id', $branchId)
                ->where('status', 'unpaid')
                ->count();

            $newUsers = User::where('branch_id', $branchId)
                ->where('created_at', '>=', $startOfMonth)
                ->count();

            $sessionAttendance = BranchSession::where('branch_id', $branchId)
                ->where('date', '>=', $startOfMonth)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            return [
                'total_revenue' => $totalRevenue,
                'total_unpaid' => $totalUnpaid,
                'new_users' => $newUsers,
                'session_attendance' => $sessionAttendance,
            ];
        });
    }

    public function clearCache(int $branchId)
    {
        Cache::forget("dashboard:branch:$branchId");
    }
}
