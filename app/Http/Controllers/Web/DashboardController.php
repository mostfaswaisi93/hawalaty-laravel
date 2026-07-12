<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Tenant\DashboardService;
use Illuminate\View\View;

/**
 * Server-rendered counterpart of Api\V1\DashboardController — same
 * DashboardService, just handed to a Blade view instead of wrapped in
 * JSON resources. See resources/views/dashboard.blade.php.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function index(): View
    {
        $stats = $this->service->stats();

        return view('dashboard', [
            'totalRevenue' => $stats['totalRevenue'],
            'orderCount' => $stats['orderCount'],
            'recentTransfers' => $stats['recentTransfers'],
            'weeklyActivity' => $this->service->weeklyActivity(),
        ]);
    }
}
