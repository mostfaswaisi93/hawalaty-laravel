<?php

namespace App\Services\Tenant;

use App\Enums\StockMovementType;
use App\Models\Tenant\Batch;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Purchase;
use App\Models\Tenant\PurchaseLineItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Goods in.
 *
 * A purchase is a draft until it is received. Receiving is the moment stock and
 * cost actually change, and it happens once: a received purchase is never edited
 * back into a draft, because the movements it posted are already part of the
 * ledger and of every valuation taken since.
 */
class PurchaseService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly BatchService $batches,
        private readonly SerialService $serials,
    ) {}

    public function list(?string $search, ?string $status, int $perPage = 20): LengthAwarePaginator
    {
        return Purchase::query()
            ->with(['supplier', 'location'])
            ->withCount('lines')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->whereSearch(['reference', 'invoice_number'], $search)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Purchase
    {
        return DB::connection('tenant')->transaction(function () use ($data) {
            $purchase = Purchase::create([
                'supplier_id' => $data['supplierId'] ?? null,
                'location_id' => $data['locationId'] ?? Location::default()?->id,
                'reference' => $data['reference'] ?? null,
                'invoice_number' => $data['invoiceNumber'] ?? null,
                'ordered_at' => $data['orderedAt'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'other_costs' => $data['otherCosts'] ?? 0,
                'status' => 'draft',
            ]);

            $this->replaceLines($purchase, $data['lines'] ?? []);

            return $purchase->fresh('lines');
        });
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        $this->assertDraft($purchase);

        return DB::connection('tenant')->transaction(function () use ($purchase, $data) {
            $purchase->update([
                'supplier_id' => $data['supplierId'] ?? null,
                'location_id' => $data['locationId'] ?? $purchase->location_id,
                'reference' => $data['reference'] ?? null,
                'invoice_number' => $data['invoiceNumber'] ?? null,
                'ordered_at' => $data['orderedAt'] ?? $purchase->ordered_at,
                'notes' => $data['notes'] ?? null,
                'other_costs' => $data['otherCosts'] ?? 0,
            ]);

            if (array_key_exists('lines', $data)) {
                $this->replaceLines($purchase, $data['lines']);
            }

            return $purchase->fresh('lines');
        });
    }

    /**
     * Post the purchase into stock.
     *
     * Landed cost matters: freight and clearing are part of what the goods cost,
     * so `other_costs` is spread across the lines by value before the weighted
     * average is rolled forward. Ignoring it understates cost of goods sold and
     * flatters every margin report built on it.
     */
    public function receive(Purchase $purchase): Purchase
    {
        $this->assertDraft($purchase);

        $purchase->load('lines');

        if ($purchase->lines->isEmpty()) {
            throw new RuntimeException(__('لا يمكن استلام أمر شراء بلا أصناف.'));
        }

        return DB::connection('tenant')->transaction(function () use ($purchase) {
            $goodsValue = $purchase->lines->sum(fn (PurchaseLineItem $line) => (float) $line->quantity * (float) $line->unit_cost);
            $otherCosts = (float) $purchase->other_costs;

            foreach ($purchase->lines as $line) {
                $item = MenuItem::find($line->menu_item_id);

                if (! $item || ! $item->isStocked()) {
                    continue;
                }

                $lineValue = (float) $line->quantity * (float) $line->unit_cost;
                $share = $goodsValue > 0 ? ($lineValue / $goodsValue) * $otherCosts : 0.0;
                $landedUnitCost = (float) $line->quantity > 0
                    ? ($lineValue + $share) / (float) $line->quantity
                    : (float) $line->unit_cost;

                $attributes = [
                    'batch_number' => $line->batch_number,
                    'expires_at' => $line->expires_at,
                    'reference_type' => 'purchase',
                    'reference_id' => $purchase->id,
                ];

                // Opened here rather than left to the ledger because this is the
                // only place that knows who supplied the lot, and a recall starts
                // by asking exactly that.
                if ($this->batches->enabled() && filled($line->batch_number)) {
                    $attributes['batch_id'] = $this->batches->findOrCreate(
                        $item,
                        (string) $line->batch_number,
                        $line->expires_at?->toDateString(),
                        $purchase->supplier_id,
                    )->id;
                }

                $this->stock->receive(
                    $item,
                    (float) $line->quantity,
                    round($landedUnitCost, 3),
                    StockMovementType::Purchase,
                    $purchase->location_id,
                    $attributes,
                );

                /*
                 * Units arriving together are interchangeable until someone
                 * picks one, so the receipt stays a single movement and the
                 * identities are recorded alongside it. What matters is that
                 * every unit gets a row now - the sale later cannot invent one.
                 */
                if ($item->track_serials && $this->serials->enabled()) {
                    $this->serials->register(
                        $item,
                        $line->serials ?? [],
                        round($landedUnitCost, 3),
                        $purchase->location_id,
                        isset($attributes['batch_id']) ? Batch::find($attributes['batch_id']) : null,
                        $purchase->id,
                    );
                }
            }

            $purchase->update([
                'status' => 'received',
                'received_at' => now(),
            ]);

            return $purchase->fresh('lines');
        });
    }

    public function cancel(Purchase $purchase): Purchase
    {
        $this->assertDraft($purchase);

        $purchase->update(['status' => 'cancelled']);

        return $purchase;
    }

    public function delete(Purchase $purchase): void
    {
        $this->assertDraft($purchase);

        $purchase->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function replaceLines(Purchase $purchase, array $lines): void
    {
        $purchase->lines()->delete();

        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            $item = MenuItem::find($line['itemId'] ?? null);

            if (! $item) {
                continue;
            }

            $quantity = (float) ($line['quantity'] ?? 0);
            $unitCost = (float) ($line['unitCost'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $net = $quantity * $unitCost;
            $rate = (float) ($line['taxRate'] ?? $item->tax_rate);
            $tax = $net * ($rate / 100);

            $purchase->lines()->create([
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'unit_code' => $item->purchaseUnit()->value,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'tax_rate' => $rate,
                'line_total' => round($net + $tax, 2),
                'batch_number' => $line['batchNumber'] ?? null,
                'expires_at' => $line['expiresAt'] ?? null,
                'serials' => $this->serialsFrom($line['serials'] ?? null),
            ]);

            $subtotal += $net;
            $taxTotal += $tax;
        }

        $purchase->update([
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($subtotal + $taxTotal + (float) $purchase->other_costs, 2),
        ]);
    }

    /**
     * Serials arrive as one-per-line text off a delivery note, which is how a
     * storekeeper actually types or scans them.
     *
     * @return list<string>|null
     */
    private function serialsFrom(mixed $raw): ?array
    {
        if (blank($raw)) {
            return null;
        }

        $values = is_array($raw) ? $raw : preg_split('/[\r\n,]+/', (string) $raw);

        $clean = collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $clean === [] ? null : $clean;
    }

    private function assertDraft(Purchase $purchase): void
    {
        if (! $purchase->isDraft()) {
            throw new RuntimeException(__('أمر الشراء بعد الاستلام لا يُعدَّل — أنشئ مرتجعًا للمورد بدلًا من ذلك.'));
        }
    }
}
