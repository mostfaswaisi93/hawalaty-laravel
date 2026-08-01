<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Backup;
use App\Models\Expense;
use App\Models\License;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Admin\FinanceSummaryService;
use App\Settings\PlatformSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const TREND_MONTHS = 6;

    public function __construct(
        private readonly FinanceSummaryService $finance,
        private readonly PlatformSettings $settings,
    ) {}

    public function index(): View
    {
        $counts = DB::table('licenses')
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $summary = $this->finance->summarize();

        $totalBoxBalance = array_sum(array_column($summary['boxes'], 'balance'));

        return view('admin.dashboard', [
            'totalLicenses' => (int) $counts->sum(),
            'activeCount' => (int) ($counts['active'] ?? 0),
            'trialCount' => (int) ($counts['trial'] ?? 0),
            'suspendedCount' => (int) ($counts['suspended'] ?? 0),
            'expiredCount' => (int) ($counts['expired'] ?? 0),
            'totalPayments' => $summary['totalPayments'],
            'totalExpenses' => $summary['totalExpenses'],

            'netProfit' => $summary['totalPayments'] - $summary['totalExpenses'],
            'boxes' => $summary['boxes'],
            'totalBoxBalance' => $totalBoxBalance,

            'partners' => $summary['partners'],
            'trend' => $this->monthlyTrend(),
            'expiringSoon' => $this->expiringSoon(),
            'packageDistribution' => $this->packageDistribution(),
            'backupStats' => $this->backupStats(),
            'recentActivity' => ActivityLog::with('admin')->latest()->take(8)->get(),
            'recentBackups' => Backup::with('license')->latest()->take(5)->get(),
        ]);
    }

    private function monthlyTrend(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(self::TREND_MONTHS - 1);

        $payments = Payment::query()
            ->where('created_at', '>=', $start)
            ->selectRaw($this->monthExpression('created_at').' as ym, SUM(amount) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $expenses = Expense::query()
            ->where('date', '>=', $start)
            ->selectRaw($this->monthExpression('date').' as ym, SUM(amount) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $series = [];

        for ($i = 0; $i < self::TREND_MONTHS; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $series[] = [
                'label' => $month->translatedFormat('M'),
                'payments' => (float) ($payments[$key] ?? 0),
                'expenses' => (float) ($expenses[$key] ?? 0),
            ];
        }

        return $series;
    }

    private function monthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private function expiringSoon()
    {
        $window = (int) $this->settings->get('license_expiry_warning_days');

        return License::query()
            ->with('package')
            ->whereIn('status', [LicenseStatus::Active->value, LicenseStatus::Trial->value])
            ->whereNotNull('expired_at')
            ->whereBetween('expired_at', [Carbon::now(), Carbon::now()->addDays($window)])
            ->orderBy('expired_at')
            ->take(5)
            ->get();
    }

    private function packageDistribution(): array
    {
        $packages = Package::query()->withCount('licenses')->orderByDesc('licenses_count')->get();
        $total = (int) $packages->sum('licenses_count');

        return $packages->map(fn (Package $package) => [
            'name' => $package->name,
            'price' => (float) $package->price,
            'count' => (int) $package->licenses_count,
            'share' => $total > 0 ? round($package->licenses_count / $total * 100) : 0.0,
        ])->all();
    }

    private function backupStats(): array
    {
        $counts = DB::table('backups')
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $completed = (int) ($counts['completed'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $total = (int) $counts->sum();

        return [
            'completed' => $completed,
            'failed' => $failed,
            'total' => $total,
            'successRate' => $total > 0 ? (int) round($completed / $total * 100) : 0,
        ];
    }
}
