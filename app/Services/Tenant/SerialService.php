<?php

namespace App\Services\Tenant;

use App\Enums\BusinessCapability;
use App\Enums\SerialStatus;
use App\Models\Tenant\Batch;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SerialNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Identity for individual units.
 *
 * A lot answers "which delivery was this from"; a serial answers "is this the
 * one we sold, to whom, and when". Electronics needs the second - a customer at
 * the counter is holding one specific handset - and so does anything with a
 * warranty attached to the unit rather than the model.
 *
 * Serials are never auto-invented. Receiving a serialised item without its
 * numbers, or selling one without saying which, would put a confident record in
 * the system that does not describe the object that actually moved.
 */
class SerialService
{
    public function __construct(private readonly BusinessProfile $business) {}

    public function enabled(): bool
    {
        return $this->business->has(BusinessCapability::Serials);
    }

    /**
     * Bring units onto the shelf, one row per physical object.
     *
     * @param  list<string>  $serials
     * @return Collection<int, SerialNumber>
     */
    public function register(
        MenuItem $item,
        array $serials,
        float $unitCost,
        ?string $locationId = null,
        ?Batch $batch = null,
        ?string $purchaseId = null,
    ): Collection {
        $serials = $this->clean($serials);

        if ($serials === []) {
            throw new RuntimeException(__('أدخل الأرقام التسلسلية للكمية المستلَمة.'));
        }

        $clash = SerialNumber::forItem($item->id)->whereIn('serial', $serials)->pluck('serial');

        if ($clash->isNotEmpty()) {
            throw new RuntimeException(__('رقم تسلسلي مسجَّل مسبقًا: :serials', [
                'serials' => $clash->take(5)->implode('، '),
            ]));
        }

        return DB::connection('tenant')->transaction(function () use ($item, $serials, $unitCost, $locationId, $batch, $purchaseId) {
            return collect($serials)->map(fn (string $serial) => SerialNumber::create([
                'menu_item_id' => $item->id,
                'serial' => $serial,
                'batch_id' => $batch?->id,
                'status' => SerialStatus::InStock,
                'location_id' => $locationId,
                'unit_cost' => $unitCost,
                'received_at' => now(),
                'purchase_id' => $purchaseId,
            ]));
        });
    }

    /**
     * Resolve the units a sale is taking, refusing anything not on the shelf.
     *
     * @param  list<string>  $serials
     * @return Collection<int, SerialNumber>
     */
    public function reserve(MenuItem $item, array $serials): Collection
    {
        $serials = $this->clean($serials);

        if ($serials === []) {
            throw new RuntimeException(__('اختر الأرقام التسلسلية المُباعة من :item.', ['item' => $item->name]));
        }

        $found = SerialNumber::forItem($item->id)
            ->whereIn('serial', $serials)
            ->get()
            ->keyBy('serial');

        foreach ($serials as $serial) {
            $unit = $found->get($serial);

            if (! $unit instanceof SerialNumber) {
                throw new RuntimeException(__('الرقم التسلسلي :serial غير مسجَّل لهذا الصنف.', ['serial' => $serial]));
            }

            if (! $unit->status->isAvailable()) {
                throw new RuntimeException(__('الرقم التسلسلي :serial ليس في المخزون (:status).', [
                    'serial' => $serial,
                    'status' => $unit->status->label(),
                ]));
            }
        }

        return $found->values();
    }

    /**
     * Mark units as gone, and start the warranty clock.
     *
     * The term is copied from the catalogue and frozen here: changing the item's
     * warranty next year must not shorten or extend what this customer was
     * already promised.
     *
     * @param  Collection<int, SerialNumber>  $units
     */
    public function markSold(Collection $units, MenuItem $item, string $transferId, ?string $customerId = null): void
    {
        $months = (int) $item->warranty_months;
        $starts = now()->toDateString();

        DB::connection('tenant')->transaction(function () use ($units, $months, $starts, $transferId, $customerId) {
            foreach ($units as $unit) {
                $unit->update([
                    'status' => SerialStatus::Sold,
                    'sold_at' => now(),
                    'transfer_id' => $transferId,
                    'customer_id' => $customerId,
                    'warranty_months' => $months,
                    'warranty_starts_at' => $months > 0 ? $starts : null,
                    'warranty_ends_at' => $months > 0 ? now()->addMonths($months)->toDateString() : null,
                ]);
            }
        });
    }

    /**
     * Put a unit back on the shelf after a return.
     *
     * The warranty is deliberately left alone: the promise was made on a date,
     * and reselling the box does not restart it for the next customer without
     * someone deciding that.
     */
    public function restock(SerialNumber $unit, ?string $locationId = null): SerialNumber
    {
        if ($unit->status === SerialStatus::Scrapped) {
            throw new RuntimeException(__('لا يمكن إعادة وحدة متلَفة إلى المخزون.'));
        }

        $unit->update([
            'status' => SerialStatus::InStock,
            'location_id' => $locationId ?? $unit->location_id,
        ]);

        return $unit;
    }

    public function scrap(SerialNumber $unit, string $reason): SerialNumber
    {
        if (trim($reason) === '') {
            throw new RuntimeException(__('اذكر سبب الإتلاف.'));
        }

        $unit->update([
            'status' => SerialStatus::Scrapped,
            'notes' => trim($reason),
        ]);

        return $unit;
    }

    /**
     * Everything known about one number, from any angle a counter question
     * arrives from.
     *
     * @return Collection<int, SerialNumber>
     */
    public function lookup(string $serial): Collection
    {
        $serial = trim($serial);

        if ($serial === '') {
            return collect();
        }

        return SerialNumber::query()
            ->with(['item', 'customer', 'transfer', 'purchase.supplier', 'batch'])
            ->where('serial', 'like', '%'.$serial.'%')
            ->orderByDesc('received_at')
            ->limit(50)
            ->get();
    }

    /**
     * Units on the shelf for an item, oldest first so the picker suggests the
     * box that has been sitting longest.
     *
     * @return Collection<int, SerialNumber>
     */
    public function availableFor(MenuItem $item, ?string $locationId = null): Collection
    {
        return SerialNumber::forItem($item->id)
            ->available()
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
            ->orderBy('received_at')
            ->get();
    }

    /**
     * Where the identity layer and the quantity ledger disagree.
     *
     * They should not: one movement is written per serial. A mismatch means
     * stock moved without its numbers - an import, a manual adjustment - and
     * every serial-based answer for that item is suspect until it is fixed.
     *
     * @return Collection<int, array{item: MenuItem, ledger: float, serials: int}>
     */
    public function mismatches(StockService $stock): Collection
    {
        $items = MenuItem::query()->where('track_serials', true)->get();

        if ($items->isEmpty()) {
            return collect();
        }

        $onHand = $stock->onHandFor($items->pluck('id')->all());

        $counts = SerialNumber::query()
            ->available()
            ->whereIn('menu_item_id', $items->pluck('id'))
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, COUNT(*) as total')
            ->pluck('total', 'menu_item_id');

        return $items
            ->map(fn (MenuItem $item) => [
                'item' => $item,
                'ledger' => $onHand[$item->id] ?? 0.0,
                'serials' => (int) ($counts[$item->id] ?? 0),
            ])
            ->filter(fn (array $row) => abs($row['ledger'] - $row['serials']) > 0.0005)
            ->values();
    }

    /**
     * Units whose cover runs out soon - the list a shop calls before it does.
     *
     * @return Collection<int, SerialNumber>
     */
    public function warrantyExpiringWithin(int $days = 30): Collection
    {
        return SerialNumber::query()
            ->with(['item', 'customer'])
            ->where('status', SerialStatus::Sold)
            ->whereNotNull('warranty_ends_at')
            ->whereDate('warranty_ends_at', '>=', now()->toDateString())
            ->whereDate('warranty_ends_at', '<=', now()->addDays($days)->toDateString())
            ->orderBy('warranty_ends_at')
            ->get();
    }

    /**
     * @param  list<string>  $serials
     * @return list<string>
     */
    private function clean(array $serials): array
    {
        return collect($serials)
            ->map(fn ($serial) => trim((string) $serial))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
