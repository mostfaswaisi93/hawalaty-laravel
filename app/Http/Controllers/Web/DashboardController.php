<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Tenant\DashboardService;
use App\Services\Tenant\ReportService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service,
        private readonly ReportService $reports,
    ) {}

    public function index(): View
    {
        $stats = $this->service->stats();

        $heatmapFrom = Carbon::today()->subYear()->addDay();
        $heatmapTo = Carbon::today();

        return view('dashboard', [
            'totalRevenue' => $stats['totalRevenue'],
            'orderCount' => $stats['orderCount'],
            'recentTransfers' => $stats['recentTransfers'],
            'weeklyActivity' => $this->service->weeklyActivity(),
            'heatmap' => $this->reports->dailyCounts($heatmapFrom->toDateString(), $heatmapTo->toDateString()),
            'heatmapFrom' => $heatmapFrom,
            'heatmapTo' => $heatmapTo,
        ]);
    }
}
