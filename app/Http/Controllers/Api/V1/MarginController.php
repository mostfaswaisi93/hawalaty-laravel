<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Tenant\MarginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MarginController extends Controller
{
    public function __construct(private readonly MarginService $margins) {}

    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json([
            'data' => [
                'from' => $from,
                'to' => $to,
                'summary' => $this->margins->summary($from, $to),
                'items' => $this->margins->perItem($from, $to)->map(fn (array $row) => $this->row($row)),
                'categories' => $this->margins->perCategory($from, $to),
            ],
        ]);
    }

    public function abc(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json([
            'data' => $this->margins->abc($from, $to)->map(fn (array $row) => array_merge($this->row($row), [
                'contribution' => $row['contribution'],
                'cumulative' => $row['cumulative'],
                'class' => $row['class'],
            ])),
        ]);
    }

    public function pricing(Request $request): JsonResponse
    {
        $target = max(0.0, min((float) $request->query('target', 30), 95.0));

        return response()->json([
            'data' => $this->margins->pricing($target)->map(fn (array $row) => [
                'id' => (string) $row['item']->id,
                'name' => $row['item']->name,
                'category' => $row['category'],
                'price' => $row['price'],
                'netPrice' => $row['net_price'],
                'cost' => $row['cost'],
                'marginPercent' => $row['margin_percent'],

                // What the margin was when the price was last set, and how far it
                // has slipped since. Null where the price predates the history.
                'marginWhenPriced' => $row['margin_when_priced'],
                'erodedBy' => $row['eroded_by'],
                'pricedAt' => $row['priced_at']?->toIso8601String(),

                'belowCost' => $row['below_cost'],
                'suggestedPrice' => $row['suggested_price'],
            ]),
            'meta' => ['target' => $target],
        ]);
    }

    public function valuation(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 90);
        $days = $days > 0 ? min($days, 3650) : 90;

        [$from, $to] = $this->range($request);
        $valuation = $this->margins->valuation();

        return response()->json([
            'data' => [
                'total' => $valuation['total'],

                // IAS 2 measures inventory at the lower of cost and net
                // realisable value; this is what that test costs today.
                'writeDown' => $valuation['writeDown'],

                'waste' => $this->margins->wasteCost($from, $to),

                'rows' => $valuation['rows']->map(fn (array $row) => [
                    'id' => (string) $row['item']->id,
                    'name' => $row['item']->name,
                    'onHand' => $row['on_hand'],
                    'unitCost' => $row['unit_cost'],
                    'value' => $row['value'],
                    'realisable' => $row['realisable'],
                    'impaired' => $row['impaired'],
                    'writeDown' => $row['write_down'],
                ]),

                'slowMovers' => $this->margins->slowMovers($days)->map(fn (array $row) => [
                    'id' => (string) $row['item']->id,
                    'name' => $row['item']->name,
                    'onHand' => $row['on_hand'],
                    'tiedUp' => $row['tied_up'],
                    'lastSold' => $row['last_sold'],
                ]),

                'consumptionVariance' => $this->margins->consumptionVariance($from, $to)->map(fn (array $row) => [
                    'id' => (string) $row['item']->id,
                    'name' => $row['item']->name,
                    'theoretical' => $row['theoretical'],
                    'actual' => $row['actual'],
                    'variance' => $row['variance'],
                    'variancePercent' => $row['variance_percent'],
                    'cost' => $row['cost'],
                ]),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(array $row): array
    {
        return [
            'id' => (string) $row['item']->id,
            'name' => $row['item']->name,
            'category' => $row['category'],
            'quantity' => $row['quantity'],
            'revenue' => $row['revenue'],
            'grossRevenue' => $row['gross_revenue'],
            'cost' => $row['cost'],
            'margin' => $row['margin'],
            'marginPercent' => $row['margin_percent'],

            // Where the cost came from: the ledger, a recipe roll-up, the item's
            // own figure, or nothing. A caller charting these should not mix them
            // silently.
            'basis' => $row['basis'],
        ];
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
}
