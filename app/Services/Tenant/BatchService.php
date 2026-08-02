<?php

namespace App\Services\Tenant;

use App\Enums\BusinessCapability;
use App\Models\Tenant\Batch;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Transfer;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Lot tracking: which lot is on the shelf, which one goes out next, and who
 * received the one being recalled.
 *
 * The recall question is the reason this exists. When a supplier withdraws a
 * lot, a shop has to answer two things within the hour: how much of it is still
 * here, and who did we sell the rest to. Neither is answerable from a quantity
 * column, which is why every issue is allocated against a specific lot and every
 * sale movement points back at its order.
 */
class BatchService
{
    public function __construct(private readonly BusinessProfile $business) {}

    public function enabled(): bool
    {
        return $this->business->has(BusinessCapability::Batches);
    }

    /**
     * Find the lot or open it. Lot numbers are the supplier's, so the same one
     * arriving twice is a second delivery of one lot, not a new lot.
     */
    public function findOrCreate(
        MenuItem $item,
        string $batchNumber,
        DateTimeInterface|string|null $expiresAt = null,
        ?string $supplierId = null,
    ): Batch {
        // Callers hand this over as a Carbon from a model cast or as a raw string
        // from a form; normalising here keeps that off every call site.
        $expiresAt = $expiresAt instanceof DateTimeInterface
            ? $expiresAt->format('Y-m-d')
            : (filled($expiresAt) ? (string) $expiresAt : null);

        $batch = Batch::firstOrCreate(
            ['menu_item_id' => $item->id, 'batch_number' => trim($batchNumber)],
            [
                'expires_at' => $expiresAt,
                'supplier_id' => $supplierId,
                'received_at' => now(),
            ],
        );

        // A lot that existed with no expiry, now arriving with one, is being
        // completed rather than contradicted. A conflicting date is left alone:
        // silently moving an expiry is exactly the edit that must never happen
        // without someone deciding it.
        if ($batch->expires_at === null && $expiresAt !== null) {
            $batch->update(['expires_at' => $expiresAt]);
        }

        return $batch;
    }

    /**
     * Split a quantity across lots, earliest expiry first.
     *
     * Expired lots are never allocated. Dispensing expired stock is the offence
     * the whole module exists to prevent, so the picker cannot reach it - it has
     * to be written off deliberately instead. If sellable lots run short the
     * remainder is returned as unallocated, and the caller decides: the sale
     * still records, and the shortfall shows up in both the negative-balance and
     * expired-on-hand reports rather than being quietly absorbed.
     *
     * @return array{allocations: list<array{batch: Batch, quantity: float}>, unallocated: float}
     */
    public function allocate(MenuItem $item, float $quantity, ?string $locationId = null): array
    {
        if ($quantity <= 0) {
            throw new RuntimeException(__('الكمية يجب أن تكون أكبر من صفر.'));
        }

        $remaining = round($quantity, 3);
        $allocations = [];

        $batches = Batch::query()
            ->where('menu_item_id', $item->id)
            ->sellable()
            ->fefo()
            ->get();

        $onHand = $this->onHandFor($batches->pluck('id')->all(), $locationId);

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = $onHand[$batch->id] ?? 0.0;

            if ($available <= 0) {
                continue;
            }

            $take = min($remaining, $available);

            $allocations[] = ['batch' => $batch, 'quantity' => round($take, 3)];
            $remaining = round($remaining - $take, 3);
        }

        return ['allocations' => $allocations, 'unallocated' => max($remaining, 0.0)];
    }

    /**
     * On-hand per lot in one query, keyed by batch id.
     *
     * @param  list<string>  $batchIds
     * @return Collection<string, float>
     */
    public function onHandFor(array $batchIds, ?string $locationId = null): Collection
    {
        if ($batchIds === []) {
            return collect();
        }

        return StockMovement::query()
            ->whereIn('batch_id', $batchIds)
            ->atLocation($locationId)
            ->groupBy('batch_id')
            ->selectRaw('batch_id, SUM(quantity) as qty')
            ->pluck('qty', 'batch_id')
            ->map(fn ($qty) => (float) $qty);
    }

    /**
     * Lots still holding stock, newest urgency first.
     *
     * @return Collection<int, array{batch: Batch, on_hand: float}>
     */
    public function inStock(?string $locationId = null, ?string $search = null): Collection
    {
        $batches = Batch::query()
            ->with(['item', 'supplier'])
            ->when($search, fn ($query) => $query->whereSearch(['batch_number'], $search))
            ->fefo()
            ->get();

        $onHand = $this->onHandFor($batches->pluck('id')->all(), $locationId);

        return $batches
            ->map(fn (Batch $batch) => ['batch' => $batch, 'on_hand' => $onHand[$batch->id] ?? 0.0])
            ->filter(fn (array $row) => abs($row['on_hand']) > 0.0005)
            ->values();
    }

    /**
     * Stock that has already expired and is still on the shelf.
     *
     * The most urgent list in the module: every unit here is unsellable and, in
     * a pharmacy or a food shop, illegal to dispense.
     *
     * @return Collection<int, array{batch: Batch, on_hand: float}>
     */
    public function expiredOnHand(?string $locationId = null): Collection
    {
        return $this->rowsFor(Batch::query()->expired()->with('item'), $locationId);
    }

    /**
     * Stock about to expire, so it can be sold, discounted or returned while it
     * still has value.
     *
     * @return Collection<int, array{batch: Batch, on_hand: float}>
     */
    public function expiringSoon(int $days = Batch::SOON_DAYS, ?string $locationId = null): Collection
    {
        return $this->rowsFor(Batch::query()->expiringWithin($days)->with('item'), $locationId);
    }

    /**
     * Value sitting in stock that has expired - the number that makes the
     * problem legible to whoever signs off on it.
     */
    public function expiredValue(?string $locationId = null): float
    {
        return $this->expiredOnHand($locationId)
            ->sum(fn (array $row) => $row['on_hand'] * (float) $row['batch']->item?->cost);
    }

    /**
     * Recall trace: the orders this lot went out on.
     *
     * Reads the ledger rather than the order lines, because the ledger is what
     * records which lot was actually picked - the order line only knows the item.
     *
     * @return Collection<int, array{transfer: Transfer, quantity: float, at: Carbon}>
     */
    public function soldOn(Batch $batch): Collection
    {
        $movements = $batch->movements()
            ->where('reference_type', 'transfer')
            ->whereNotNull('reference_id')
            ->orderByDesc('created_at')
            ->get();

        $transfers = Transfer::query()
            ->whereIn('id', $movements->pluck('reference_id')->unique()->all())
            ->get()
            ->keyBy('id');

        return $movements
            ->map(fn (StockMovement $movement) => [
                'transfer' => $transfers->get($movement->reference_id),
                'quantity' => abs((float) $movement->quantity),
                'at' => $movement->created_at,
            ])
            ->filter(fn (array $row) => $row['transfer'] !== null)
            ->values();
    }

    /**
     * @return Collection<int, array{batch: Batch, on_hand: float}>
     */
    private function rowsFor(Builder $query, ?string $locationId): Collection
    {
        $batches = $query->fefo()->get();
        $onHand = $this->onHandFor($batches->pluck('id')->all(), $locationId);

        return $batches
            ->map(fn (Batch $batch) => ['batch' => $batch, 'on_hand' => $onHand[$batch->id] ?? 0.0])
            ->filter(fn (array $row) => $row['on_hand'] > 0.0005)
            ->values();
    }
}
