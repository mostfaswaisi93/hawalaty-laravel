<?php

namespace App\Services\Tenant;

use App\Enums\StockMovementType;
use App\Models\Tenant\Batch;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only writer of the stock ledger.
 *
 * Two rules hold everywhere in here:
 *
 *  1. Movements are appended, never edited. A wrong movement is answered with an
 *     opposite one so the history stays readable and the sync channel stays
 *     conflict-free.
 *
 *  2. On-hand is derived. There is no cached balance to drift out of agreement
 *     with the rows that produced it.
 *
 * Costing is weighted average, which IAS 2 permits alongside FIFO and which is
 * the sane default for a shop: it needs no layer tracking and survives partial
 * receipts. Each inflow recomputes the item's average; each outflow is stamped
 * with the average as it stood, so cost of goods sold is recorded rather than
 * recalculated later against a number that has since moved.
 */
class StockService
{
    public function __construct(
        private readonly BusinessProfile $business,
        private readonly BatchService $batches,
        private readonly RecipeService $recipes,
    ) {}

    /**
     * On-hand quantity for an item, optionally at one location.
     */
    public function onHand(string $itemId, ?string $locationId = null): float
    {
        return (float) StockMovement::query()
            ->forItem($itemId)
            ->atLocation($locationId)
            ->sum('quantity');
    }

    /**
     * On-hand for many items in one query, keyed by item id.
     *
     * The catalog screen shows a balance per row; asking per row would be a query
     * per item.
     *
     * @param  list<string>  $itemIds
     * @return Collection<string, float>
     */
    public function onHandFor(array $itemIds, ?string $locationId = null): Collection
    {
        if ($itemIds === []) {
            return collect();
        }

        return StockMovement::query()
            ->whereIn('menu_item_id', $itemIds)
            ->atLocation($locationId)
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, SUM(quantity) as qty')
            ->pluck('qty', 'menu_item_id')
            ->map(fn ($qty) => (float) $qty);
    }

    /**
     * Total value of stock on hand, at weighted-average cost.
     *
     * Valued from the movements rather than quantity x current cost, because the
     * two disagree the moment a cost changes and the ledger is the version an
     * auditor can follow.
     */
    public function stockValue(?string $locationId = null): float
    {
        return (float) StockMovement::query()
            ->atLocation($locationId)
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as value')
            ->value('value');
    }

    /**
     * Record an inflow and roll the item's weighted-average cost forward.
     *
     * The new average is (value on hand + value received) / (quantity on hand +
     * quantity received). When stock is negative or zero - which happens after a
     * sale posted before its receipt - there is no meaningful average to blend
     * with, so the incoming cost simply becomes the cost.
     */
    public function receive(
        MenuItem $item,
        float $quantity,
        float $unitCost,
        StockMovementType $type = StockMovementType::Purchase,
        ?string $locationId = null,
        array $attributes = [],
    ): StockMovement {
        $this->assertStocked($item);
        $this->assertPositive($quantity);

        return DB::connection('tenant')->transaction(function () use ($item, $quantity, $unitCost, $type, $locationId, $attributes) {
            $locationId = $this->resolveLocation($locationId);
            $attributes = $this->resolveBatch($item, $attributes);

            $onHand = $this->onHand($item->id);
            $currentCost = (float) $item->cost;

            $newCost = $onHand > 0
                ? (($onHand * $currentCost) + ($quantity * $unitCost)) / ($onHand + $quantity)
                : $unitCost;

            $movement = StockMovement::create(array_merge([
                'menu_item_id' => $item->id,
                'location_id' => $locationId,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'team_member_id' => auth('tenant')->id(),
            ], $attributes));

            $item->update(['cost' => round($newCost, 3)]);

            return $movement;
        });
    }

    /**
     * Record an outflow at the item's current average cost.
     *
     * Returns a collection because one issue can become several rows: when the
     * vertical tracks lots the quantity is split across them earliest-expiry
     * first, and a sale that spans two lots has to be recorded as two lines or
     * the recall trace is a lie.
     *
     * Deliberately does not refuse to go negative. A shop that sells the last
     * unit while the receipt is still in a drawer is describing reality, and
     * blocking the sale to protect a number would be the wrong trade-off at the
     * till. The negative balance is surfaced as an exception to fix, not hidden.
     *
     * @return Collection<int, StockMovement>
     */
    public function issue(
        MenuItem $item,
        float $quantity,
        StockMovementType $type = StockMovementType::Sale,
        ?string $locationId = null,
        array $attributes = [],
    ): Collection {
        // Silent rather than throwing: this runs at the till, inside the order
        // transaction. A vertical with no inventory module has no ledger to
        // write to, and a services workspace must not fail a sale over it.
        if (! $this->business->tracksStock()) {
            return collect();
        }

        // A dish assembled at the till is not held as a dish. What leaves the
        // shelf is its ingredients, exploded down to whatever is actually
        // counted, so a burger takes bread and meat rather than a burger.
        if ($this->recipes->enabled() && $item->assemblesToOrder()) {
            $this->assertPositive($quantity);

            return $this->issueComponents($item, $quantity, $locationId, $attributes);
        }

        if (! $item->isStocked()) {
            return collect();
        }

        $this->assertPositive($quantity);

        $locationId = $this->resolveLocation($locationId);

        if (! $this->batches->enabled() || array_key_exists('batch_id', $attributes)) {
            return collect([$this->writeIssue($item, $quantity, $type, $locationId, $attributes)]);
        }

        return DB::connection('tenant')->transaction(function () use ($item, $quantity, $type, $locationId, $attributes) {
            $picked = $this->batches->allocate($item, $quantity, $locationId);
            $movements = collect();

            foreach ($picked['allocations'] as $allocation) {
                $batch = $allocation['batch'];

                $movements->push($this->writeIssue($item, $allocation['quantity'], $type, $locationId, array_merge($attributes, [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'expires_at' => $batch->expires_at,
                ])));
            }

            // Nothing sellable left to pick from. The movement is still written,
            // unallocated, so the sale is recorded and the shortfall shows up in
            // the negative-balance report instead of vanishing.
            if ($picked['unallocated'] > 0) {
                $movements->push($this->writeIssue($item, $picked['unallocated'], $type, $locationId, $attributes));
            }

            return $movements;
        });
    }

    /**
     * Write off a specific lot, whole or in part.
     *
     * Expired stock is never picked by FEFO, so the only way it leaves the
     * ledger is here - deliberately, with a reason, by someone who chose to.
     */
    public function writeOffBatch(Batch $batch, float $quantity, string $reason, ?string $locationId = null): StockMovement
    {
        $item = $batch->item;

        if (! $item instanceof MenuItem) {
            throw new RuntimeException(__('اللوط غير مرتبط بصنف.'));
        }

        $this->assertStocked($item);
        $this->assertPositive($quantity);

        if (trim($reason) === '') {
            throw new RuntimeException(__('الإتلاف بدون سبب غير مسموح.'));
        }

        $locationId = $this->resolveLocation($locationId);
        $available = $batch->onHand($locationId);

        if ($quantity > $available + 0.0005) {
            throw new RuntimeException(__('الكمية أكبر من رصيد اللوط المتاح.'));
        }

        return $this->writeIssue($item, $quantity, StockMovementType::Waste, $locationId, [
            'batch_id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'expires_at' => $batch->expires_at,
            'reason' => $reason,
        ]);
    }

    /**
     * Deplete a recipe's ingredients instead of the finished item.
     *
     * Recorded as consumption rather than a sale: what was sold is the order,
     * and what left the shelf is flour. Keeping the two apart is what lets a
     * kitchen compare theoretical against actual usage at all.
     *
     * @return Collection<int, StockMovement>
     */
    private function issueComponents(MenuItem $item, float $quantity, ?string $locationId, array $attributes): Collection
    {
        $movements = collect();

        foreach ($this->recipes->explode($item, $quantity) as $row) {
            $movements = $movements->concat($this->issue(
                $row['item'],
                $row['quantity'],
                StockMovementType::Consumption,
                $locationId,
                array_merge($attributes, [
                    'reason' => $attributes['reason'] ?? __('مكوّنات :item', ['item' => $item->name]),
                ]),
            ));
        }

        return $movements;
    }

    private function writeIssue(
        MenuItem $item,
        float $quantity,
        StockMovementType $type,
        string $locationId,
        array $attributes,
    ): StockMovement {
        return StockMovement::create(array_merge([
            'menu_item_id' => $item->id,
            'location_id' => $locationId,
            'type' => $type,
            'quantity' => -1 * abs($quantity),
            'unit_cost' => (float) $item->cost,
            'team_member_id' => auth('tenant')->id(),
        ], $attributes));
    }

    /**
     * Any inflow of a lot-tracked item has to land in a lot.
     *
     * Resolved here rather than at the call site so a receipt written from
     * anywhere - a purchase, an import, a future returns flow - cannot leave a
     * batch number floating with no lot behind it.
     */
    private function resolveBatch(MenuItem $item, array $attributes): array
    {
        if (! $this->batches->enabled()
            || filled($attributes['batch_id'] ?? null)
            || blank($attributes['batch_number'] ?? null)) {
            return $attributes;
        }

        $batch = $this->batches->findOrCreate(
            $item,
            (string) $attributes['batch_number'],
            $attributes['expires_at'] ?? null,
        );

        $attributes['batch_id'] = $batch->id;
        $attributes['expires_at'] = $batch->expires_at;

        return $attributes;
    }

    /**
     * Correct a balance to a known figure.
     *
     * Posts the difference, not the target, so the ledger still reads as a series
     * of changes. A reason is required: an adjustment without one is the thing
     * stock control exists to prevent.
     */
    public function adjust(MenuItem $item, float $countedQuantity, string $reason, ?string $locationId = null): ?StockMovement
    {
        $this->assertStocked($item);

        if (trim($reason) === '') {
            throw new RuntimeException(__('التسوية بدون سبب غير مسموحة.'));
        }

        $locationId = $this->resolveLocation($locationId);
        $delta = round($countedQuantity - $this->onHand($item->id, $locationId), 3);

        if (abs($delta) < 0.0005) {
            return null;
        }

        return StockMovement::create([
            'menu_item_id' => $item->id,
            'location_id' => $locationId,
            'type' => StockMovementType::Adjustment,
            'quantity' => $delta,
            'unit_cost' => (float) $item->cost,
            'reason' => $reason,
            'team_member_id' => auth('tenant')->id(),
        ]);
    }

    /**
     * Write stock off. Separate from an adjustment because the reason is the
     * point: a bakery's daily write-off is a cost line to manage, not a counting
     * error to explain away.
     *
     * @return Collection<int, StockMovement>
     */
    public function waste(MenuItem $item, float $quantity, string $reason, ?string $locationId = null): Collection
    {
        return $this->issue($item, $quantity, StockMovementType::Waste, $locationId, ['reason' => $reason]);
    }

    /**
     * Move stock between locations as two mirrored rows.
     *
     * Both sides are written in one transaction; a transfer that lands only half
     * way would invent or destroy stock.
     *
     * @return array{0: StockMovement, 1: StockMovement}
     */
    public function transfer(MenuItem $item, float $quantity, string $fromLocationId, string $toLocationId): array
    {
        $this->assertStocked($item);
        $this->assertPositive($quantity);

        if ($fromLocationId === $toLocationId) {
            throw new RuntimeException(__('لا يمكن النقل إلى نفس الموقع.'));
        }

        return DB::connection('tenant')->transaction(function () use ($item, $quantity, $fromLocationId, $toLocationId) {
            $cost = (float) $item->cost;
            $memberId = auth('tenant')->id();

            $out = StockMovement::create([
                'menu_item_id' => $item->id,
                'location_id' => $fromLocationId,
                'type' => StockMovementType::Transfer,
                'quantity' => -1 * $quantity,
                'unit_cost' => $cost,
                'team_member_id' => $memberId,
            ]);

            $in = StockMovement::create([
                'menu_item_id' => $item->id,
                'location_id' => $toLocationId,
                'type' => StockMovementType::Transfer,
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'reference_type' => 'stock_movement',
                'reference_id' => $out->id,
                'team_member_id' => $memberId,
            ]);

            return [$out, $in];
        });
    }

    /**
     * Items at or below their reorder point.
     *
     * A shop with three thousand lines cannot notice this by eye, and the whole
     * value of holding a reorder point is that something reads it.
     */
    public function belowReorderPoint(?string $locationId = null): Collection
    {
        $items = MenuItem::query()
            ->stocked()
            ->active()
            ->whereNotNull('reorder_point')
            ->get();

        $onHand = $this->onHandFor($items->pluck('id')->all(), $locationId);

        return $items
            ->map(fn (MenuItem $item) => [
                'item' => $item,
                'on_hand' => $onHand[$item->id] ?? 0.0,
            ])
            ->filter(fn (array $row) => $row['on_hand'] <= (float) $row['item']->reorder_point)
            ->sortBy('on_hand')
            ->values();
    }

    /**
     * Items whose balance has gone below zero.
     *
     * Always a data problem - an unrecorded receipt, a double-posted sale - and
     * always worth showing, because every valuation built on it is wrong.
     */
    public function negativeBalances(?string $locationId = null): Collection
    {
        return StockMovement::query()
            ->atLocation($locationId)
            ->groupBy('menu_item_id')
            ->havingRaw('SUM(quantity) < 0')
            ->selectRaw('menu_item_id, SUM(quantity) as qty')
            ->with('item')
            ->get();
    }

    private function resolveLocation(?string $locationId): string
    {
        if ($locationId !== null) {
            return $locationId;
        }

        $default = Location::default();

        if (! $default) {
            throw new RuntimeException(__('لا يوجد موقع مخزني. أضف موقعًا أولًا.'));
        }

        return $default->id;
    }

    private function assertStocked(MenuItem $item): void
    {
        if (! $this->business->tracksStock()) {
            throw new RuntimeException(__('المخزون غير مفعّل لتصنيف نشاطك.'));
        }

        if (! $item->isStocked()) {
            throw new RuntimeException(__('هذا الصنف لا يخضع لتتبّع المخزون.'));
        }
    }

    private function assertPositive(float $quantity): void
    {
        if ($quantity <= 0) {
            throw new RuntimeException(__('الكمية يجب أن تكون أكبر من صفر.'));
        }
    }
}
