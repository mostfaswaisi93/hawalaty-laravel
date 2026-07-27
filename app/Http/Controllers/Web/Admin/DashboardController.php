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

/**
 * Server-rendered Super Admin home. Counts licenses by status (via the query
 * builder so the LicenseStatus cast never gets used as an array key) and
 * reuses FinanceSummaryService for the money figures.
 */
class DashboardController extends Controller
{
    /** How many months of revenue/expense history the trend chart shows. */
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
            // Net is the headline number the other two only support, so it is
            // computed here rather than left for the view to subtract.
            'netProfit' => $summary['totalPayments'] - $summary['totalExpenses'],
            'boxes' => $summary['boxes'],
            'totalBoxBalance' => $totalBoxBalance,
            // FinanceSummaryService already computes each partner's share of the
            // box balance on every call; the dashboard used to discard it.
            'partners' => $summary['partners'],
            'trend' => $this->monthlyTrend(),
            'expiringSoon' => $this->expiringSoon(),
            'packageDistribution' => $this->packageDistribution(),
            'backupStats' => $this->backupStats(),
            'recentActivity' => ActivityLog::with('admin')->latest()->take(8)->get(),
            'recentBackups' => Backup::with('license')->latest()->take(5)->get(),
        ]);
    }

    /**
     * Revenue vs. expenses for the last TREND_MONTHS months, always returning
     * one entry per month (zero-filled) so the chart never has gaps.
     *
     * Two GROUP BY queries rather than a per-month loop - same reasoning as the
     * tenant dashboard's weekly-activity aggregate.
     *
     * @return array<int, array{label: string, payments: float, expenses: float}>
     */
    private function monthlyTrend(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(self::TREND_MONTHS - 1);

        $payments = Payment::query()
            ->where('created_at', '>=', $start)
            ->selectRaw($this->monthExpression('created_at').' as ym, SUM(amount) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym');

        // Expenses are dated by their own `date` column, not created_at - an
        // expense can be back-dated when recorded.
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

    /**
     * A `YYYY-MM` bucket expression for the active driver. Grouping stays in
     * SQL (so this is one query per series rather than a per-month loop), but
     * MySQL's DATE_FORMAT does not exist in sqlite - which the test suite uses,
     * per phpunit.xml - so the equivalent strftime is emitted there instead.
     *
     * $column is only ever an internal constant from this class, never request
     * input, so interpolating it into raw SQL is safe.
     */
    private function monthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /**
     * Active/trial licenses lapsing soon - the one thing on this page that
     * needs the admin to actually do something. The window is configurable at
     * /admin/settings (`license_expiry_warning_days`); it used to be a private
     * const here, which meant changing it required a deploy.
     */
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

    /**
     * Licenses per package, with each package's share of the total, so the
     * admin can see which plans actually carry the business.
     *
     * @return array<int, array{name: string, price: float, count: int, share: float}>
     */
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

    /**
     * @return array{completed: int, failed: int, total: int, successRate: int}
     */
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
