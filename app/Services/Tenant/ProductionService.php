<?php

namespace App\Services\Tenant;

use App\Enums\StockMovementType;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\ProductionRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Making finished goods out of components.
 *
 * The bakery half of recipes. A restaurant assembles at the till and never comes
 * here; a bakery bakes four hundred loaves at six in the morning, and that one
 * event both consumes flour and creates loaves.
 *
 * Posting is one way, like receiving a purchase: it has already moved stock in
 * two directions and the movements are part of every valuation taken since.
 */
class ProductionService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly RecipeService $recipes,
        private readonly BatchService $batches,
    ) {}

    public function list(?string $status, int $perPage = 20): LengthAwarePaginator
    {
        return ProductionRun::query()
            ->with(['item', 'location', 'teamMember'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): ProductionRun
    {
        $item = MenuItem::findOrFail($data['itemId']);

        if (! $item->hasRecipe()) {
            throw new RuntimeException(__('لا يمكن إنتاج صنف بلا وصفة.'));
        }

        if (! $item->isStocked()) {
            throw new RuntimeException(__('هذا الصنف يُجهَّز عند الطلب ولا يُنتَج للمخزون.'));
        }

        return ProductionRun::create([
            'menu_item_id' => $item->id,
            'location_id' => $data['locationId'] ?? Location::default()?->id,
            'quantity' => (float) $data['quantity'],
            'status' => 'draft',
            'batch_number' => $data['batchNumber'] ?? null,
            'expires_at' => $data['expiresAt'] ?? null,
            'notes' => $data['notes'] ?? null,
            'team_member_id' => auth('tenant')->id(),
        ]);
    }

    /**
     * Consume the components and create the goods.
     *
     * Cost flows through: what the run consumed at the components' average costs
     * becomes the unit cost of the output, so a loaf is valued at what its flour
     * and labour-free inputs actually cost that morning rather than at a price
     * someone typed once.
     */
    public function post(ProductionRun $run): ProductionRun
    {
        $this->assertDraft($run);

        $item = $run->item;

        if (! $item instanceof MenuItem) {
            throw new RuntimeException(__('أمر الإنتاج غير مرتبط بصنف.'));
        }

        $quantity = (float) $run->quantity;

        if ($quantity <= 0) {
            throw new RuntimeException(__('كمية الإنتاج يجب أن تكون أكبر من صفر.'));
        }

        return DB::connection('tenant')->transaction(function () use ($run, $item, $quantity) {
            $components = $this->recipes->explode($item, $quantity);

            if ($components->isEmpty()) {
                throw new RuntimeException(__('وصفة هذا الصنف فارغة.'));
            }

            $consumedValue = 0.0;

            foreach ($components as $row) {
                $component = $row['item'];
                $consumedValue += $row['quantity'] * (float) $component->cost;

                $this->stock->issue(
                    $component,
                    $row['quantity'],
                    StockMovementType::Consumption,
                    $run->location_id,
                    [
                        'reference_type' => 'production',
                        'reference_id' => $run->id,
                        'reason' => __('إنتاج :item', ['item' => $item->name]),
                    ],
                );
            }

            $unitCost = round($consumedValue / $quantity, 3);

            $attributes = [
                'reference_type' => 'production',
                'reference_id' => $run->id,
                'reason' => __('دفعة إنتاج'),
            ];

            // Freshly baked goods expire within the day; the run opens the lot.
            if ($this->batches->enabled() && filled($run->batch_number)) {
                $batch = $this->batches->findOrCreate($item, (string) $run->batch_number, $run->expires_at?->toDateString());

                $attributes['batch_id'] = $batch->id;
                $attributes['batch_number'] = $batch->batch_number;
                $attributes['expires_at'] = $batch->expires_at;
            }

            $this->stock->receive(
                $item,
                $quantity,
                $unitCost,
                StockMovementType::Production,
                $run->location_id,
                $attributes,
            );

            $run->update([
                'status' => 'posted',
                'produced_at' => now(),
                'unit_cost' => $unitCost,
            ]);

            return $run->fresh(['item', 'location']);
        });
    }

    public function cancel(ProductionRun $run): ProductionRun
    {
        $this->assertDraft($run);

        $run->update(['status' => 'cancelled']);

        return $run;
    }

    public function delete(ProductionRun $run): void
    {
        $this->assertDraft($run);

        $run->delete();
    }

    private function assertDraft(ProductionRun $run): void
    {
        if (! $run->isDraft()) {
            throw new RuntimeException(__('أمر الإنتاج بعد الترحيل لا يُعدَّل — سجّل تسوية أو إتلافًا بدلًا من ذلك.'));
        }
    }
}
