<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\BusinessProfile;
use App\Services\Tenant\CatalogNaming;
use App\Services\Tenant\MarginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MarginController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly MarginService $margins,
        private readonly CatalogNaming $naming,
        private readonly BusinessProfile $business,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->canView()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لتقارير الهامش.'));
        }

        [$from, $to] = $this->range($request);

        $view = $request->query('view', 'items');
        $view = in_array($view, ['items', 'categories', 'abc'], true) ? $view : 'items';
        $perPage = $this->perPage($request, 20);

        /*
         * The aggregate is computed once for the whole period - it has to be,
         * because a total of one page is not a total - and only the rendering is
         * paged. A catalogue of a few thousand lines costs the same handful of
         * queries either way; what does not scale is emitting every row as HTML.
         */
        return view('margins.index', [
            'from' => $from,
            'to' => $to,
            'view' => $view,
            'perPage' => $perPage,

            'summary' => $this->margins->summary($from, $to),
            'items' => $this->page($this->margins->perItem($from, $to), $perPage, $request),
            'categories' => $this->margins->perCategory($from, $to),
            'abc' => $this->page($this->margins->abc($from, $to), $perPage, $request),

            'naming' => $this->naming,
            'tracksStock' => $this->business->tracksStock(),
        ]);
    }

    /**
     * The pricing side: where the margin is thin, gone, or has slipped since the
     * price was set.
     */
    public function pricing(Request $request): View|RedirectResponse
    {
        if (! $this->canView()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لتقارير الهامش.'));
        }

        $target = (float) $request->query('target', 30);
        $target = max(0, min($target, 95));

        $perPage = $this->perPage($request, 20);
        $rows = $this->margins->pricing($target);

        return view('margins.pricing', [
            'rows' => $this->page($rows, $perPage, $request),

            // Counted over the whole result, not the page: "three items sold
            // below cost" must not become "three on this page".
            'belowCost' => $rows->where('below_cost', true)->count(),
            'eroded' => $rows->filter(fn (array $row) => $row['eroded_by'] !== null && $row['eroded_by'] >= 5)->count(),
            'total' => $rows->count(),

            'target' => $target,
            'perPage' => $perPage,
            'naming' => $this->naming,
        ]);
    }

    /**
     * What the money is sitting in, and what IAS 2 says has to come off it.
     */
    public function valuation(Request $request): View|RedirectResponse
    {
        if (! $this->canView()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لتقارير الهامش.'));
        }

        $days = (int) $request->query('days', 90);
        $days = in_array($days, [30, 60, 90, 180, 365], true) ? $days : 90;

        [$from, $to] = $this->range($request);

        return view('margins.valuation', [
            'valuation' => $this->margins->valuation(),
            'slowMovers' => $this->margins->slowMovers($days),
            'variance' => $this->margins->consumptionVariance($from, $to),
            'waste' => $this->margins->wasteCost($from, $to),
            'days' => $days,
            'from' => $from,
            'to' => $to,
            'naming' => $this->naming,
        ]);
    }

    public function history(string $item): View|RedirectResponse
    {
        if (! $this->canView()) {
            return redirect()->route('dashboard')->with('error', __('ليست لديك صلاحية لتقارير الهامش.'));
        }

        $record = MenuItem::findOrFail($item);

        return view('margins.history', [
            'item' => $record,
            'changes' => $this->margins->priceHistory($record),
            'naming' => $this->naming,
        ]);
    }

    /**
     * Page an already-computed collection.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function page(Collection $rows, int $perPage, Request $request): LengthAwarePaginator
    {
        $current = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $rows->forPage($current, $perPage)->values(),
            $rows->count(),
            $perPage,
            $current,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            $validated['from'] ?? Carbon::now()->startOfMonth()->toDateString(),
            $validated['to'] ?? Carbon::now()->toDateString(),
        ];
    }

    private function canView(): bool
    {
        return (bool) auth('tenant')->user()?->hasPermission(PermissionKey::MenuManage);
    }
}
