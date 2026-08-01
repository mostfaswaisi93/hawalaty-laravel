<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Services\Tenant\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly ReportService $service) {}

    public function index(Request $request): View
    {
        return view('reports.index', $this->buildReport($request, paginate: true));
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

    private function buildReport(Request $request, bool $paginate = false): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => [
                'nullable',
                'date',
                'after_or_equal:from',

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

            'transfers' => $paginate
                ? $this->service->transfersPage($from, $to, $perPage)
                : $this->service->generate($from, $to)['transfers'],
            'perPage' => $perPage,
            'daily' => $this->service->dailyTotals($from, $to),

            'heatmapFrom' => $heatmapFrom = Carbon::today()->subYear()->addDay(),
            'heatmapTo' => $heatmapTo = Carbon::today(),
            'heatmap' => $this->service->dailyCounts($heatmapFrom->toDateString(), $heatmapTo->toDateString()),
        ];
    }
}
