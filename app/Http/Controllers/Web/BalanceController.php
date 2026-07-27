<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Tenant\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * الموازنة المالية - money in versus money out over a period, per account.
 *
 * Read-only. Every figure comes from BalanceService, which owns the rules about
 * what counts (completed orders only, salaries as a subtotal inside expenses,
 * change deliberately not netted).
 */
class BalanceController extends Controller
{
    public function __construct(private readonly BalanceService $service) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Defaults to the current month, matching the reports page so moving
        // between the two does not silently change the period.
        $from = $validated['from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? Carbon::now()->toDateString();

        return view('balance.index', [
            'from' => $from,
            'to' => $to,
            'summary' => $this->service->summary($from, $to),
            'accounts' => $this->service->byAccount($from, $to),
        ]);
    }
}
