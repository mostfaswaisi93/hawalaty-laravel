<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Tenant\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Read-only reports (sidebar permission=null - any authenticated member).
 * Defaults to the current month when no range is submitted; otherwise the
 * same from/to rules as Tenant\Report\ReportRequest (relaxed to nullable so
 * the default can apply) and delegates to ReportService::generate().
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    public function index(Request $request): View
    {
        return view('reports.index', $this->buildReport($request));
    }

    public function export(Request $request): Response
    {
        $report = $this->buildReport($request);

        $license = app()->bound('currentLicense') ? app('currentLicense') : null;

        $pdf = Pdf::loadView('reports.pdf', [
            ...$report,
            'businessName' => $license?->customer_name ?? __('حوالاتي'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("report-{$report['from']}-to-{$report['to']}.pdf");
    }

    /** @return array{from: string, to: string, count: int, total: float, average: float, transfers: Collection, daily: Collection} */
    private function buildReport(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => [
                'nullable',
                'date',
                'after_or_equal:from',
                // A truly unbounded range (e.g. from a hand-crafted URL) would
                // otherwise pull every transfer the tenant has ever recorded
                // into one PDF/HTML render - cap it to a year, generous for
                // any normal reporting window.
                function ($attribute, $value, $fail) use ($request) {
                    $from = $request->input('from');
                    if ($from && Carbon::parse($from)->diffInDays(Carbon::parse($value)) > 366) {
                        $fail(__('لا يمكن أن يتجاوز نطاق التقرير 366 يوماً.'));
                    }
                },
            ],
        ]);

        $from = $validated['from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? Carbon::now()->toDateString();

        $summary = $this->service->summary($from, $to);
        $perPage = $this->perPage($request, 20);

        return [
            'from' => $from,
            'to' => $to,
            'count' => $summary['count'],
            'total' => $summary['total'],
            'average' => $summary['average'],
            // Paginated for the screen, complete for the export - the headline
            // figures above come from aggregates either way, so they stay right
            // whichever page is shown.
            'transfers' => $paginate
                ? $this->service->transfersPage($from, $to, $perPage)
                : $this->service->generate($from, $to)['transfers'],
            'perPage' => $perPage,
            'daily' => $this->service->dailyTotals($from, $to),
        ];
    }
}
