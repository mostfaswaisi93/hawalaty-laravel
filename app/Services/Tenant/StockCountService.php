<?php

namespace App\Services\Tenant;

use App\Enums\StockMovementType;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\StockCount;
use App\Models\Tenant\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Physical stocktake.
 *
 * Opening a sheet snapshots what the ledger believes, so the variance stays
 * meaningful even though the shop keeps trading while the count is under way.
 * Posting writes one `count` movement per line that actually differs - a sheet
 * where everything matched should leave no trace in the ledger beyond the sheet
 * itself.
 */
class StockCountService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Open a sheet over every stocked item at a location.
     */
    public function open(string $locationId, ?string $reference = null, ?string $notes = null): StockCount
    {
        return DB::connection('tenant')->transaction(function () use ($locationId, $reference, $notes) {
            $count = StockCount::create([
                'location_id' => $locationId,
                'reference' => $reference,
                'notes' => $notes,
                'status' => 'open',
                'team_member_id' => auth('tenant')->id(),
            ]);

            $items = MenuItem::query()->stocked()->active()->orderBy('name')->get();
            $onHand = $this->stock->onHandFor($items->pluck('id')->all(), $locationId);

            foreach ($items as $item) {
                $count->lines()->create([
                    'menu_item_id' => $item->id,
                    'expected_quantity' => $onHand[$item->id] ?? 0,
                    'counted_quantity' => null,
                    'unit_cost' => (float) $item->cost,
                ]);
            }

            return $count->fresh('lines');
        });
    }

    /**
     * Save counted figures. Lines left blank stay uncounted, which is not the
     * same as counted-zero and must not be posted as a write-off.
     *
     * @param  array<string, mixed>  $counted  line id => quantity
     */
    public function record(StockCount $count, array $counted): StockCount
    {
        $this->assertOpen($count);

        DB::connection('tenant')->transaction(function () use ($count, $counted) {
            foreach ($count->lines as $line) {
                if (! array_key_exists($line->id, $counted)) {
                    continue;
                }

                $value = $counted[$line->id];

                $line->update([
                    'counted_quantity' => ($value === null || $value === '') ? null : (float) $value,
                ]);
            }
        });

        return $count->fresh('lines');
    }

    /**
     * Post the variances into the ledger and close the sheet.
     */
    public function post(StockCount $count): StockCount
    {
        $this->assertOpen($count);

        $count->load('lines');

        if ($count->lines->whereNotNull('counted_quantity')->isEmpty()) {
            throw new RuntimeException(__('لم تُدخل أي كمية مجرودة بعد.'));
        }

        return DB::connection('tenant')->transaction(function () use ($count) {
            $memberId = auth('tenant')->id();

            foreach ($count->lines as $line) {
                if ($line->counted_quantity === null) {
                    continue;
                }

                // Recomputed against the balance right now, not against the
                // snapshot: movements posted while the sheet was open are real,
                // and adjusting to the stale expectation would undo them.
                $current = $this->stock->onHand($line->menu_item_id, $count->location_id);
                $delta = round((float) $line->counted_quantity - $current, 3);

                if (abs($delta) < 0.0005) {
                    continue;
                }

                StockMovement::create([
                    'menu_item_id' => $line->menu_item_id,
                    'location_id' => $count->location_id,
                    'type' => StockMovementType::Count,
                    'quantity' => $delta,
                    'unit_cost' => (float) $line->unit_cost,
                    'reference_type' => 'stock_count',
                    'reference_id' => $count->id,
                    'reason' => __('فرق جرد :reference', ['reference' => $count->reference ?: $count->id]),
                    'team_member_id' => $memberId,
                ]);
            }

            $count->update(['status' => 'posted', 'posted_at' => now()]);

            return $count->fresh('lines');
        });
    }

    public function cancel(StockCount $count): StockCount
    {
        $this->assertOpen($count);

        $count->update(['status' => 'cancelled']);

        return $count;
    }

    private function assertOpen(StockCount $count): void
    {
        if (! $count->isOpen()) {
            throw new RuntimeException(__('هذا الجرد مُرحَّل أو ملغى ولا يقبل التعديل.'));
        }
    }
}
