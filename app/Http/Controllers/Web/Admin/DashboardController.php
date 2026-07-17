<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Backup;
use App\Services\Admin\FinanceSummaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Server-rendered Super Admin home. Counts licenses by status (via the query
 * builder so the LicenseStatus cast never gets used as an array key) and
 * reuses FinanceSummaryService for the money figures.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly FinanceSummaryService $finance) {}

    public function index(): View
    {
        $counts = DB::table('licenses')
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $summary = $this->finance->summarize();

        return view('admin.dashboard', [
            'totalLicenses' => (int) $counts->sum(),
            'activeCount' => (int) ($counts['active'] ?? 0),
            'trialCount' => (int) ($counts['trial'] ?? 0),
            'suspendedCount' => (int) ($counts['suspended'] ?? 0),
            'expiredCount' => (int) ($counts['expired'] ?? 0),
            'totalPayments' => $summary['totalPayments'],
            'totalExpenses' => $summary['totalExpenses'],
            'boxes' => $summary['boxes'],
            'recentActivity' => ActivityLog::with('admin')->latest()->take(8)->get(),
            'recentBackups' => Backup::with('license')->latest()->take(5)->get(),
        ]);
    }
}
