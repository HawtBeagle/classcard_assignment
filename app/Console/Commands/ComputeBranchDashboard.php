<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Branch;
use App\Services\DashboardService;

class ComputeBranchDashboard extends Command
{
    protected $signature = 'dashboard:compute';
    protected $description = 'Precompute dashboard metrics for all branches';

    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        parent::__construct();
        $this->dashboardService = $dashboardService;
    }

    public function handle()
    {
        $branches = Branch::all();

        foreach ($branches as $branch) {
            $this->dashboardService->getBranchMetrics($branch->id);
            $this->info("Dashboard cached for branch {$branch->id}");
        }
    }
}
