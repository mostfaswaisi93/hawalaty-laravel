<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Tenant\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Read-only reports (sidebar permission=null — any authenticated member).
 * Defaults to the current month when no range is submitted; otherwise the
 * same from/to rules as Tenant\Report\ReportRequest (relaxed to nullable so
 * the default can apply) and delegates to ReportService::generate().
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? Carbon::now()->toDateString();

        $report = $this->service->generate($from, $to);

        // Aggregate per-day revenue for the bar chart (service returns raw rows).
        $daily = collect($report['transfers'])
            ->groupBy(fn ($t) => $t->created_at->toDateString())
            ->map(fn ($rows) => (float) $rows->sum('amount'))
            ->sortKeys();

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'count' => $report['count'],
            'total' => $report['total'],
            'average' => $report['average'],
            'transfers' => $report['transfers'],
            'daily' => $daily,
        ]);
    }
}
