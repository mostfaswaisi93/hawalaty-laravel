<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Enums\StockMovementType;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\StockMovement;
use App\Services\Tenant\StockService;
use RuntimeException;
use Tests\TenantTestCase;

class StockServiceTest extends TenantTestCase
{
    private function service(): StockService
    {
        return app(StockService::class);
    }

    private function item(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'H87',
            'cost' => 0,
        ], $attributes));
    }

    private function location(): Location
    {
        return Location::default() ?? Location::create([
            'name' => 'Test store',
            'kind' => 'store',
            'is_default' => true,
            'active' => true,
        ]);
    }

    public function test_on_hand_is_the_sum_of_movements(): void
    {
        $item = $this->item();
        $this->location();

        $this->service()->receive($item, 10, 5);
        $this->service()->issue($item, 3);

        $this->assertSame(7.0, $this->service()->onHand($item->id));
    }

    public function test_nothing_writes_a_balance_column(): void
    {
        $item = $this->item();
        $this->location();

        $this->service()->receive($item, 4, 2);

        // Two rows, not one mutated number - this is what makes the ledger
        // auditable and what makes offline sync safe.
        $this->assertSame(1, StockMovement::forItem($item->id)->count());
        $this->assertFalse(array_key_exists('stock', $item->fresh()->getAttributes()));
    }

    public function test_receiving_rolls_the_weighted_average_cost_forward(): void
    {
        $item = $this->item();
        $this->location();

        $this->service()->receive($item, 10, 10);
        $this->service()->receive($item, 10, 20);

        // (10 x 10 + 10 x 20) / 20 = 15
        $this->assertSame('15.000', $item->fresh()->cost);
    }

    public function test_the_first_receipt_sets_the_cost_outright(): void
    {
        $item = $this->item(['cost' => 99]);
        $this->location();

        $this->service()->receive($item, 5, 7);

        $this->assertSame('7.000', $item->fresh()->cost);
    }

    public function test_issuing_freezes_the_cost_of_the_moment(): void
    {
        $item = $this->item();
        $this->location();

        $this->service()->receive($item, 10, 10);
        $sale = $this->service()->issue($item, 2);

        $this->service()->receive($item, 10, 30);

        // The sale keeps the cost it was made at, so cost of goods sold does not
        // move retroactively when a later delivery arrives dearer.
        $this->assertSame('10.000', $sale->fresh()->unit_cost);
    }

    public function test_issue_writes_a_negative_quantity(): void
    {
        $item = $this->item();
        $this->location();

        $this->service()->receive($item, 10, 1);
        $movement = $this->service()->issue($item, 4);

        $this->assertSame('-4.000', $movement->quantity);
        $this->assertSame(StockMovementType::Sale, $movement->type);
    }

    public function test_stock_is_allowed_to_go_negative(): void
    {
        $item = $this->item();
        $this->location();

        // A shop selling the last unit before the paperwork catches up is
        // describing reality; refusing the sale would be the wrong trade-off.
        $this->service()->issue($item, 3);

        $this->assertSame(-3.0, $this->service()->onHand($item->id));
    }

    public function test_services_are_never_stocked(): void
    {
        $item = $this->item(['kind' => 'service']);
        $this->location();

        $this->assertNull($this->service()->issue($item, 1));
        $this->assertSame(0, StockMovement::forItem($item->id)->count());
    }

    public function test_adjustment_posts_the_difference_not_the_target(): void
    {
        $item = $this->item();
        $this->location();

        $this->service()->receive($item, 10, 1);
        $movement = $this->service()->adjust($item, 7, 'خطأ جرد سابق');

        $this->assertSame('-3.000', $movement->quantity);
        $this->assertSame(7.0, $this->service()->onHand($item->id));
    }

    public function test_adjustment_to_the_same_quantity_writes_nothing(): void
    {
        $item = $this->item();
        $this->location();

        $this->service()->receive($item, 10, 1);

        $this->assertNull($this->service()->adjust($item, 10, 'مطابق'));
        $this->assertSame(1, StockMovement::forItem($item->id)->count());
    }

    public function test_adjustment_without_a_reason_is_refused(): void
    {
        $item = $this->item();
        $this->location();

        $this->expectException(RuntimeException::class);

        $this->service()->adjust($item, 5, '   ');
    }

    public function test_transfer_writes_both_sides(): void
    {
        $item = $this->item();
        $from = $this->location();
        $to = Location::create(['name' => 'مستودع', 'kind' => 'warehouse', 'active' => true]);

        $this->service()->receive($item, 10, 2, StockMovementType::Purchase, $from->id);
        [$out, $in] = $this->service()->transfer($item, 4, $from->id, $to->id);

        $this->assertSame('-4.000', $out->quantity);
        $this->assertSame('4.000', $in->quantity);

        $this->assertSame(6.0, $this->service()->onHand($item->id, $from->id));
        $this->assertSame(4.0, $this->service()->onHand($item->id, $to->id));

        // Total is untouched: a transfer moves stock, it does not create it.
        $this->assertSame(10.0, $this->service()->onHand($item->id));
    }

    public function test_transfer_to_the_same_location_is_refused(): void
    {
        $item = $this->item();
        $location = $this->location();

        $this->expectException(RuntimeException::class);

        $this->service()->transfer($item, 1, $location->id, $location->id);
    }

    public function test_stock_value_is_read_from_the_movements(): void
    {
        $item = $this->item();
        $this->location();

        $this->service()->receive($item, 10, 10);
        $this->service()->issue($item, 4);

        // 10 x 10 received, 4 x 10 issued = 60 remaining at cost.
        $this->assertSame(60.0, $this->service()->stockValue());
    }

    public function test_below_reorder_point_lists_only_items_that_reached_it(): void
    {
        $low = $this->item(['name' => 'قليل', 'reorder_point' => 5]);
        $fine = $this->item(['name' => 'كافٍ', 'reorder_point' => 5]);
        $untracked = $this->item(['name' => 'بلا حد', 'reorder_point' => null]);
        $this->location();

        $this->service()->receive($low, 3, 1);
        $this->service()->receive($fine, 20, 1);
        $this->service()->receive($untracked, 1, 1);

        $names = $this->service()->belowReorderPoint()->pluck('item.name')->all();

        $this->assertSame(['قليل'], $names);
    }

    public function test_negative_balances_are_surfaced(): void
    {
        $item = $this->item(['name' => 'سالب']);
        $this->location();

        $this->service()->issue($item, 2);

        $this->assertSame(['سالب'], $this->service()->negativeBalances()->pluck('item.name')->all());
    }

    public function test_stock_is_refused_when_the_vertical_has_no_inventory(): void
    {
        $this->bindCurrentLicense(BusinessType::Services);

        $item = $this->item();
        $this->location();

        $this->expectException(RuntimeException::class);

        app(StockService::class)->receive($item, 1, 1);
    }
}
