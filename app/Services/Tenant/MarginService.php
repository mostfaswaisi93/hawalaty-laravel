<?php

namespace App\Services\Tenant;

use App\Enums\StockMovementType;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\PriceChange;
use App\Models\Tenant\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the shop actually earns, as opposed to what it takes.
 *
 * Turnover is the number every till reports and the least useful one in the
 * building. This module answers the harder question: after what the goods cost,
 * what is left - per item, per category, and where it is leaking.
 *
 * Three things it is careful about, because they are where margin reports
 * usually go quietly wrong:
 *
 *  - **Cost comes from the ledger, not from the item.** Every outflow was stamped
 *    with the weighted average as it stood at that moment. Multiplying today's
 *    cost by last month's quantity would revalue history every time a supplier
 *    changes a price, and last month's margin would move while you watched it.
 *
 *  - **Revenue is net of the order discount and net of tax.** A discount reduces
 *    what was earned; tax was never the shop's. Reporting either gross inflates
 *    the margin, and the inflation is largest exactly where discounts are
 *    heaviest, which is where the report is most likely to be read.
 *
 *  - **An assembled item's cost is theoretical, and says so.** What leaves the
 *    shelf for a sandwich is bread and cheese, and when an order has four
 *    sandwiches there is no way back from a flour movement to the dish that
 *    caused it. The per-dish figure is the recipe rolled up at current component
 *    costs; the real number lives in the consumption variance below, which is
 *    where over-portioning and theft actually show up.
 */
class MarginService
{
    /**
     * Outflows that represent goods sold. Waste, transfers and count differences
     * are costs too, but they are not cost of sales and folding them in here
     * would make a bad month look like a pricing problem.
     */
    private const SOLD = [StockMovementType::Sale, StockMovementType::Consumption];

    /**
     * Per-period cache. The summary, the ABC list and the per-category roll-up
     * are all the same scan; a screen showing all three would otherwise run it
     * three times over the same rows.
     *
     * @var array<string, Collection<int, array<string, mixed>>>
     */
    private array $computed = [];

    public function __construct(
        private readonly BusinessProfile $business,
        private readonly RecipeService $recipes,
        private readonly StockService $stock,
    ) {}

    /**
     * Margin per item over a period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function perItem(string $from, string $to): Collection
    {
        return $this->computed["{$from}|{$to}"] ??= $this->computePerItem($from, $to);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function computePerItem(string $from, string $to): Collection
    {
        $revenue = $this->revenueByItem($from, $to);
        $cost = $this->costByItem($from, $to);

        $ids = collect($revenue->keys())->merge($cost->keys())->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $items = MenuItem::query()->with('menuCategory')->whereIn('id', $ids)->get()->keyBy('id');

        return $ids
            ->map(function (string $id) use ($revenue, $cost, $items) {
                $item = $items->get($id);

                if (! $item instanceof MenuItem) {
                    return null;
                }

                $sold = $revenue->get($id, ['quantity' => 0.0, 'net' => 0.0, 'gross' => 0.0]);
                $ledgerCost = (float) $cost->get($id, 0.0);
                $basis = 'ledger';

                // Nothing left the shelf under this item's name. Either it is
                // assembled from components, or it is a service, or the shop does
                // not track stock at all - and those are three different answers.
                if ($ledgerCost <= 0 && $sold['quantity'] > 0) {
                    [$ledgerCost, $basis] = $this->inferredCost($item, $sold['quantity']);
                }

                $net = $sold['net'];
                $margin = round($net - $ledgerCost, 2);

                return [
                    'item' => $item,
                    'category' => $item->menuCategory?->name,
                    'quantity' => round($sold['quantity'], 3),
                    'revenue' => round($net, 2),
                    'gross_revenue' => round($sold['gross'], 2),
                    'cost' => round($ledgerCost, 2),
                    'margin' => $margin,
                    'margin_percent' => $net > 0 ? round(($margin / $net) * 100, 1) : null,
                    'basis' => $basis,
                ];
            })
            ->filter()
            ->sortByDesc('margin')
            ->values();
    }

    /**
     * The same figures rolled up by category.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function perCategory(string $from, string $to): Collection
    {
        return $this->perItem($from, $to)
            ->groupBy(fn (array $row) => $row['category'] ?? __('بلا فئة'))
            ->map(function (Collection $rows, string $category) {
                $revenue = round($rows->sum('revenue'), 2);
                $margin = round($rows->sum('margin'), 2);

                return [
                    'category' => $category,
                    'items' => $rows->count(),
                    'quantity' => round($rows->sum('quantity'), 3),
                    'revenue' => $revenue,
                    'cost' => round($rows->sum('cost'), 2),
                    'margin' => $margin,
                    'margin_percent' => $revenue > 0 ? round(($margin / $revenue) * 100, 1) : null,
                ];
            })
            ->sortByDesc('margin')
            ->values();
    }

    /**
     * Headline figures, plus what the period cost in things that were not sold.
     *
     * Waste is reported beside the margin rather than inside it: it is a real
     * cost and a separate decision. A bakery with a healthy margin and a large
     * write-off has a production problem, not a pricing one, and merging the two
     * hides which.
     *
     * @return array<string, mixed>
     */
    public function summary(string $from, string $to): array
    {
        $rows = $this->perItem($from, $to);

        $revenue = round($rows->sum('revenue'), 2);
        $cost = round($rows->sum('cost'), 2);
        $margin = round($revenue - $cost, 2);
        $waste = $this->wasteCost($from, $to);

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'margin' => $margin,
            'margin_percent' => $revenue > 0 ? round(($margin / $revenue) * 100, 1) : null,
            'waste' => $waste,
            'margin_after_waste' => round($margin - $waste, 2),
            'items' => $rows->count(),
        ];
    }

    /**
     * ABC classification on margin contribution.
     *
     * Pareto: the top 80% of contribution is A, the next 15% B, the rest C. On
     * margin rather than revenue on purpose - a high-turnover item sold at cost
     * is a busy way of earning nothing, and a revenue-ranked list puts it at the
     * top where it will get the attention.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function abc(string $from, string $to): Collection
    {
        $rows = $this->perItem($from, $to)->filter(fn (array $row) => $row['margin'] > 0)->values();
        $total = $rows->sum('margin');

        if ($total <= 0) {
            return collect();
        }

        $cumulative = 0.0;

        return $rows->map(function (array $row) use (&$cumulative, $total) {
            /*
             * An item takes the band it *starts* in, not the one it ends in.
             * Classifying on the running total after the item would put the top
             * contributor in C whenever it is large enough to cross 95 on its
             * own - and the biggest earner in the shop landing in the ignore-me
             * bucket is exactly backwards.
             */
            $opening = ($cumulative / $total) * 100;

            $cumulative += $row['margin'];

            return array_merge($row, [
                'contribution' => round(($row['margin'] / $total) * 100, 1),
                'cumulative' => round(($cumulative / $total) * 100, 1),
                'class' => $opening < 80 ? 'A' : ($opening < 95 ? 'B' : 'C'),
            ]);
        });
    }

    /**
     * Stock that has not moved.
     *
     * Reported as capital tied up rather than as a count of items, because that
     * is the figure a decision gets made on: a hundred dead lines worth nothing
     * are a tidying job, and one dead line worth a month's rent is not.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function slowMovers(int $days = 90): Collection
    {
        if (! $this->business->tracksStock()) {
            return collect();
        }

        $since = now()->subDays($days);

        $moved = StockMovement::query()
            ->whereIn('type', self::SOLD)
            ->where('created_at', '>=', $since)
            ->distinct()
            ->pluck('menu_item_id')
            ->all();

        $items = MenuItem::query()
            ->stocked()
            ->active()
            ->whereNotIn('id', $moved)
            ->with('menuCategory')
            ->get();

        $onHand = $this->stock->onHandFor($items->pluck('id')->all());

        // One query for the whole list. Asking per row is how a report that runs
        // in a second on a demo takes a minute on a real catalogue.
        $lastSold = StockMovement::query()
            ->whereIn('menu_item_id', $items->pluck('id'))
            ->whereIn('type', self::SOLD)
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, MAX(created_at) as sold_at')
            ->pluck('sold_at', 'menu_item_id');

        return $items
            ->map(function (MenuItem $item) use ($onHand, $lastSold) {
                $quantity = (float) ($onHand[$item->id] ?? 0.0);

                return [
                    'item' => $item,
                    'on_hand' => round($quantity, 3),
                    'tied_up' => round($quantity * (float) $item->cost, 2),

                    // Null means never sold at all, which is a different and worse
                    // fact than "not sold recently".
                    'last_sold' => $lastSold[$item->id] ?? null,
                ];
            })
            ->filter(fn (array $row) => $row['on_hand'] > 0)
            ->sortByDesc('tied_up')
            ->values();
    }

    /**
     * Stock valuation, and the write-downs IAS 2 requires.
     *
     * The standard measures inventory at the lower of cost and net realisable
     * value. An item whose cost has climbed past what it can be sold for is
     * carried above what it will ever return, and the difference is a loss that
     * has already happened whether or not anyone has written it down.
     *
     * @return array<string, mixed>
     */
    public function valuation(): array
    {
        if (! $this->business->tracksStock()) {
            return ['total' => 0.0, 'writeDown' => 0.0, 'rows' => collect()];
        }

        $items = MenuItem::query()->stocked()->active()->with('menuCategory')->get();
        $onHand = $this->stock->onHandFor($items->pluck('id')->all());

        $rows = $items
            ->map(function (MenuItem $item) use ($onHand) {
                $quantity = (float) ($onHand[$item->id] ?? 0.0);

                if ($quantity <= 0) {
                    return null;
                }

                $cost = (float) $item->cost;
                $realisable = $item->netPrice();

                // Only meaningful where a selling price exists. A component that
                // is never sold on its own has no realisable value of its own.
                $impaired = $realisable > 0 && $cost > $realisable;

                return [
                    'item' => $item,
                    'on_hand' => round($quantity, 3),
                    'unit_cost' => round($cost, 3),
                    'value' => round($quantity * $cost, 2),
                    'realisable' => round($realisable, 3),
                    'impaired' => $impaired,
                    'write_down' => $impaired ? round($quantity * ($cost - $realisable), 2) : 0.0,
                ];
            })
            ->filter()
            ->sortByDesc('value')
            ->values();

        return [
            'total' => round($rows->sum('value'), 2),
            'writeDown' => round($rows->sum('write_down'), 2),
            'rows' => $rows,
        ];
    }

    /**
     * Where the margin is thin, gone, or has quietly eroded.
     *
     * The third case is the one worth building for. An item priced at a healthy
     * margin two years ago, whose cost has climbed every quarter since while
     * nobody revisited the price, does not look wrong on any screen - it looks
     * like an item with a price. The comparison against the cost recorded when
     * the price was set is what makes it visible.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pricing(float $targetMargin = 30.0): Collection
    {
        $items = MenuItem::query()->sellable()->active()->with('menuCategory')->get();

        // One query for the whole catalogue rather than one per row. Ordered
        // oldest first so that keyBy - which keeps the last write for a repeated
        // key - leaves the newest change per item.
        $lastChange = PriceChange::query()
            ->whereIn('menu_item_id', $items->pluck('id'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->keyBy('menu_item_id');

        return $items
            ->map(function (MenuItem $item) use ($lastChange, $targetMargin) {
                $net = $item->netPrice();
                $cost = $this->currentCost($item);

                if ($cost <= 0) {
                    return null;
                }

                $margin = $net > 0 ? round((($net - $cost) / $net) * 100, 1) : null;
                $change = $lastChange->get($item->id);
                $costThen = $change ? (float) $change->cost_at_change : null;

                $marginThen = $costThen !== null && $costThen > 0 && $net > 0
                    ? round((($this->netOf($item, (float) $change->new_price) - $costThen) / $this->netOf($item, (float) $change->new_price)) * 100, 1)
                    : null;

                return [
                    'item' => $item,
                    'category' => $item->menuCategory?->name,
                    'price' => round((float) $item->price, 3),
                    'net_price' => round($net, 3),
                    'cost' => round($cost, 3),
                    'margin_percent' => $margin,

                    // What the margin was when the price was last set, and how far
                    // it has slipped since.
                    'margin_when_priced' => $marginThen,
                    'eroded_by' => $marginThen !== null && $margin !== null ? round($marginThen - $margin, 1) : null,
                    'priced_at' => $change?->created_at,

                    'below_cost' => $net > 0 && $net < $cost,
                    'suggested_price' => $this->priceFor($item, $cost, $targetMargin),
                ];
            })
            ->filter()
            ->filter(fn (array $row) => $row['below_cost']
                || ($row['margin_percent'] !== null && $row['margin_percent'] < $targetMargin)
                || ($row['eroded_by'] !== null && $row['eroded_by'] >= 5))
            ->sortBy('margin_percent')
            ->values();
    }

    /**
     * Theoretical against actual ingredient usage.
     *
     * The recipe says a hundred sandwiches take ten kilos of cheese. The ledger
     * says twelve left the fridge. That two-kilo gap is the only number in this
     * whole module that a kitchen can act on the same day, and it is invisible in
     * every per-dish margin figure.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function consumptionVariance(string $from, string $to): Collection
    {
        if (! $this->recipes->enabled()) {
            return collect();
        }

        $sold = $this->revenueByItem($from, $to);
        $expected = [];

        $items = MenuItem::query()->whereIn('id', $sold->keys())->get();

        foreach ($items as $item) {
            if (! $item->assemblesToOrder()) {
                continue;
            }

            $quantity = (float) ($sold[$item->id]['quantity'] ?? 0);

            foreach ($this->recipes->explode($item, $quantity) as $row) {
                $componentId = $row['item']->id;
                $expected[$componentId] = ($expected[$componentId] ?? 0.0) + $row['quantity'];
            }
        }

        if ($expected === []) {
            return collect();
        }

        $actual = StockMovement::query()
            ->where('type', StockMovementType::Consumption)
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->whereIn('menu_item_id', array_keys($expected))
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, SUM(-quantity) as used')
            ->pluck('used', 'menu_item_id');

        $components = MenuItem::query()->whereIn('id', array_keys($expected))->get()->keyBy('id');

        return collect($expected)
            ->map(function (float $theoretical, string $id) use ($actual, $components) {
                $item = $components->get($id);

                if (! $item instanceof MenuItem) {
                    return null;
                }

                $used = (float) ($actual[$id] ?? 0.0);
                $variance = round($used - $theoretical, 3);

                return [
                    'item' => $item,
                    'theoretical' => round($theoretical, 3),
                    'actual' => round($used, 3),
                    'variance' => $variance,
                    'variance_percent' => $theoretical > 0 ? round(($variance / $theoretical) * 100, 1) : null,
                    'cost' => round($variance * (float) $item->cost, 2),
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row) => abs($row['cost']))
            ->values();
    }

    /**
     * What was written off over the period, at the cost it was carried at.
     */
    public function wasteCost(string $from, string $to): float
    {
        return round((float) StockMovement::query()
            ->where('type', StockMovementType::Waste)
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->selectRaw('COALESCE(SUM(-quantity * unit_cost), 0) as cost')
            ->value('cost'), 2);
    }

    /**
     * Every recorded price move for one item, newest first.
     *
     * @return Collection<int, PriceChange>
     */
    public function priceHistory(MenuItem $item): Collection
    {
        return PriceChange::query()
            ->where('menu_item_id', $item->id)
            ->with('teamMember')
            ->latest()
            ->get();
    }

    /**
     * Revenue per item, net of the order discount and net of tax.
     *
     * The discount lives on the order, not the line, so it is apportioned across
     * the lines in proportion to what each contributed. `transfers.amount` is
     * already the discounted figure, which makes the ratio a straight division.
     *
     * Streamed in one pass ordered by order id, so a year of sales does not have
     * to fit in memory at once.
     *
     * @return Collection<string, array{quantity: float, net: float, gross: float}>
     */
    private function revenueByItem(string $from, string $to): Collection
    {
        $modifiers = DB::connection('tenant')
            ->table('transfer_line_item_modifiers')
            ->select('transfer_line_item_id')
            ->selectRaw('SUM(price_delta) as delta')
            ->groupBy('transfer_line_item_id');

        $rows = DB::connection('tenant')
            ->table('transfer_line_items as li')
            ->join('transfers as t', 't.id', '=', 'li.transfer_id')
            ->leftJoinSub($modifiers, 'm', 'm.transfer_line_item_id', '=', 'li.id')
            ->whereNull('t.deleted_at')
            ->whereBetween('t.created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->orderBy('li.transfer_id')
            ->select([
                'li.transfer_id',
                'li.menu_item_id',
                'li.quantity',
                'li.price',
                't.amount',
            ])
            ->selectRaw('COALESCE(m.delta, 0) as delta')
            ->cursor();

        /*
         * Tax is read as the catalogue holds it today, not as it stood at the
         * sale. That is acceptable here and would not be on an invoice: this is a
         * management figure, and the invoice froze its own rates at issue.
         */
        $tax = MenuItem::query()
            ->get(['id', 'tax_rate', 'tax_included'])
            ->keyBy('id')
            ->map(fn (MenuItem $item) => [
                'rate' => (float) $item->tax_rate,
                'inclusive' => (bool) $item->tax_included,
            ]);

        $totals = [];
        $buffer = [];
        $currentTransfer = null;
        $currentAmount = 0.0;

        $flush = function () use (&$buffer, &$totals, &$currentAmount, $tax) {
            if ($buffer === []) {
                return;
            }

            $gross = array_sum(array_column($buffer, 'gross'));
            $ratio = $gross > 0 ? $currentAmount / $gross : 1.0;

            foreach ($buffer as $line) {
                $id = $line['id'];

                if ($id === null) {
                    continue;
                }

                $paid = $line['gross'] * $ratio;
                $config = $tax[$id] ?? ['rate' => 0.0, 'inclusive' => true];
                $net = $config['inclusive'] && $config['rate'] > 0
                    ? $paid / (1 + $config['rate'] / 100)
                    : $paid;

                $totals[$id] ??= ['quantity' => 0.0, 'net' => 0.0, 'gross' => 0.0];
                $totals[$id]['quantity'] += $line['quantity'];
                $totals[$id]['net'] += $net;
                $totals[$id]['gross'] += $paid;
            }

            $buffer = [];
        };

        foreach ($rows as $row) {
            if ($row->transfer_id !== $currentTransfer) {
                $flush();
                $currentTransfer = $row->transfer_id;
                $currentAmount = (float) $row->amount;
            }

            $buffer[] = [
                'id' => $row->menu_item_id,
                'quantity' => (float) $row->quantity,
                'gross' => (float) $row->quantity * ((float) $row->price + (float) $row->delta),
            ];
        }

        $flush();

        return collect($totals);
    }

    /**
     * Cost of goods sold per item, taken from the frozen unit cost on each
     * outflow rather than recomputed against today's average.
     *
     * @return Collection<string, float>
     */
    private function costByItem(string $from, string $to): Collection
    {
        return StockMovement::query()
            ->where('type', StockMovementType::Sale)
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, SUM(-quantity * unit_cost) as cost')
            ->pluck('cost', 'menu_item_id')
            ->map(fn ($cost) => (float) $cost);
    }

    /**
     * Cost for something the ledger has nothing to say about.
     *
     * @return array{0: float, 1: string}
     */
    private function inferredCost(MenuItem $item, float $quantity): array
    {
        if ($this->recipes->enabled() && $item->hasRecipe()) {
            return [round($this->recipes->theoreticalCost($item) * $quantity, 2), 'recipe'];
        }

        // A service, or a vertical with no inventory. Its cost is labour and
        // overhead, which this system does not carry, so reporting zero is
        // honest and reporting the item's nominal cost would not be.
        if ($item->isService() || ! $this->business->tracksStock()) {
            return [0.0, 'none'];
        }

        return [round((float) $item->cost * $quantity, 2), 'standard'];
    }

    private function currentCost(MenuItem $item): float
    {
        return $this->recipes->enabled() && $item->hasRecipe()
            ? $this->recipes->theoreticalCost($item)
            : (float) $item->cost;
    }

    /**
     * The shelf price that would hit a target margin, expressed the same way the
     * item is priced so it can be typed straight in.
     */
    private function priceFor(MenuItem $item, float $cost, float $targetMargin): float
    {
        if ($targetMargin >= 100) {
            return round((float) $item->price, 3);
        }

        $net = $cost / (1 - ($targetMargin / 100));
        $rate = (float) $item->tax_rate;

        return round($item->tax_included && $rate > 0 ? $net * (1 + $rate / 100) : $net, 2);
    }

    private function netOf(MenuItem $item, float $price): float
    {
        $rate = (float) $item->tax_rate;

        return $item->tax_included && $rate > 0 ? $price / (1 + $rate / 100) : $price;
    }
}
