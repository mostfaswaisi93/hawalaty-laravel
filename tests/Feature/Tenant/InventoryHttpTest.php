<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Enums\StockMovementType;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Purchase;
use App\Models\Tenant\StockCount;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\BatchService;
use App\Services\Tenant\StockService;
use Tests\TenantTestCase;

class InventoryHttpTest extends TenantTestCase
{
    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    private function stockedItem(): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'H87',
        ]);
    }

    private function location(): Location
    {
        return Location::default() ?? Location::create([
            'name' => 'Test store', 'kind' => 'store', 'is_default' => true, 'active' => true,
        ]);
    }

    public function test_the_inventory_page_renders(): void
    {
        $item = $this->stockedItem();
        $this->location();
        app(StockService::class)->receive($item, 10, 4);

        $this->actingAs($this->owner(), 'tenant')
            ->get('/inventory')
            ->assertOk()
            ->assertSee($item->name);
    }

    public function test_the_item_ledger_page_renders(): void
    {
        $item = $this->stockedItem();
        $this->location();
        app(StockService::class)->receive($item, 5, 2);

        $this->actingAs($this->owner(), 'tenant')
            ->get("/inventory/{$item->id}")
            ->assertOk()
            ->assertSee($item->name);
    }

    public function test_the_suppliers_purchases_and_stocktake_pages_render(): void
    {
        $owner = $this->owner();
        $this->location();

        foreach (['/suppliers', '/purchases', '/purchases/create', '/stock-counts'] as $path) {
            $this->actingAs($owner, 'tenant')->get($path)->assertOk();
        }
    }

    public function test_an_adjustment_posts_the_difference(): void
    {
        $item = $this->stockedItem();
        $this->location();
        app(StockService::class)->receive($item, 10, 1);

        $this->actingAs($this->owner(), 'tenant')
            ->post('/inventory/movements', [
                'itemId' => $item->id,
                'type' => StockMovementType::Adjustment->value,
                'quantity' => '6',
                'reason' => 'خطأ جرد سابق',
            ])
            ->assertRedirect();

        $this->assertSame(6.0, app(StockService::class)->onHand($item->id));
    }

    public function test_an_adjustment_without_a_reason_is_rejected(): void
    {
        $item = $this->stockedItem();
        $this->location();

        $this->actingAs($this->owner(), 'tenant')
            ->post('/inventory/movements', [
                'itemId' => $item->id,
                'type' => StockMovementType::Adjustment->value,
                'quantity' => '6',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_a_sale_movement_cannot_be_typed_in_by_hand(): void
    {
        $item = $this->stockedItem();
        $this->location();

        $this->actingAs($this->owner(), 'tenant')
            ->post('/inventory/movements', [
                'itemId' => $item->id,
                'type' => StockMovementType::Sale->value,
                'quantity' => '3',
                'reason' => 'محاولة',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_a_batch_tracking_vertical_refuses_a_line_without_lot_and_expiry(): void
    {
        // Grocery tracks batches, so the receipt cannot be posted blind.
        $location = $this->location();
        $item = $this->stockedItem();

        $this->actingAs($this->owner(), 'tenant')
            ->post('/purchases', [
                'locationId' => $location->id,
                'lines' => [['itemId' => $item->id, 'quantity' => '1', 'unitCost' => '1']],
            ])
            ->assertSessionHasErrors(['lines.0.batchNumber', 'lines.0.expiresAt']);
    }

    public function test_a_vertical_without_batches_is_not_asked_for_a_lot(): void
    {
        $this->bindCurrentLicense(BusinessType::Bookstore);

        $location = $this->location();
        $item = $this->stockedItem();

        $this->actingAs($this->owner(), 'tenant')
            ->post('/purchases', [
                'locationId' => $location->id,
                'lines' => [['itemId' => $item->id, 'quantity' => '1', 'unitCost' => '1']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Purchase::count());
    }

    public function test_receiving_a_purchase_posts_stock_and_lands_the_freight_into_cost(): void
    {
        $owner = $this->owner();
        $location = $this->location();
        $item = $this->stockedItem();
        $supplier = Supplier::create(['name' => 'مورد تجريبي', 'active' => true]);

        $this->actingAs($owner, 'tenant')
            ->post('/purchases', [
                'supplierId' => $supplier->id,
                'locationId' => $location->id,
                'otherCosts' => '50',
                'lines' => [
                    [
                        'itemId' => $item->id,
                        'quantity' => '10',
                        'unitCost' => '10',
                        'taxRate' => '0',
                        'batchNumber' => 'LOT-1',
                        'expiresAt' => now()->addYear()->toDateString(),
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $purchase = Purchase::sole();
        $this->assertSame('draft', $purchase->status);

        // Nothing moves while it is a draft.
        $this->assertSame(0.0, app(StockService::class)->onHand($item->id));

        $this->actingAs($owner, 'tenant')
            ->post("/purchases/{$purchase->id}/receive")
            ->assertRedirect();

        $this->assertSame('received', $purchase->fresh()->status);
        $this->assertSame(10.0, app(StockService::class)->onHand($item->id));

        // (10 x 10 goods + 50 freight) / 10 units = 15 landed per unit.
        $this->assertSame('15.000', $item->fresh()->cost);
    }

    public function test_a_received_purchase_cannot_be_edited(): void
    {
        $owner = $this->owner();
        $location = $this->location();
        $item = $this->stockedItem();

        $purchase = Purchase::create([
            'location_id' => $location->id,
            'status' => 'received',
            'received_at' => now(),
        ]);

        $this->actingAs($owner, 'tenant')
            ->put("/purchases/{$purchase->id}", [
                'locationId' => $location->id,
                'lines' => [[
                    'itemId' => $item->id,
                    'quantity' => '1',
                    'unitCost' => '1',
                    'batchNumber' => 'LOT-2',
                    'expiresAt' => now()->addYear()->toDateString(),
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_a_stocktake_opens_snapshots_records_and_posts(): void
    {
        $owner = $this->owner();
        $location = $this->location();
        $item = $this->stockedItem();

        app(StockService::class)->receive($item, 10, 3);

        $this->actingAs($owner, 'tenant')
            ->post('/stock-counts', ['locationId' => $location->id, 'reference' => 'جرد الشهر'])
            ->assertRedirect();

        $count = StockCount::with('lines')->sole();
        $line = $count->lines->firstWhere('menu_item_id', $item->id);

        $this->assertSame('10.000', $line->expected_quantity);

        $this->actingAs($owner, 'tenant')
            ->put("/stock-counts/{$count->id}", ['counted' => [$line->id => '8']])
            ->assertRedirect();

        $this->actingAs($owner, 'tenant')
            ->post("/stock-counts/{$count->id}/post")
            ->assertRedirect();

        $this->assertSame('posted', $count->fresh()->status);
        $this->assertSame(8.0, app(StockService::class)->onHand($item->id));

        $variance = StockMovement::where('type', StockMovementType::Count)->sole();
        $this->assertSame('-2.000', $variance->quantity);
    }

    public function test_an_uncounted_line_is_not_treated_as_zero(): void
    {
        $owner = $this->owner();
        $location = $this->location();
        $item = $this->stockedItem();

        app(StockService::class)->receive($item, 10, 3);

        $this->actingAs($owner, 'tenant')
            ->post('/stock-counts', ['locationId' => $location->id]);

        $count = StockCount::with('lines')->sole();

        // Posting with nothing counted must refuse rather than write everything off.
        $this->actingAs($owner, 'tenant')
            ->post("/stock-counts/{$count->id}/post")
            ->assertSessionHas('error');

        $this->assertSame(10.0, app(StockService::class)->onHand($item->id));
    }

    public function test_the_inventory_pages_are_closed_to_a_vertical_without_stock(): void
    {
        $this->bindCurrentLicense(BusinessType::Services);

        $owner = $this->owner();

        foreach (['/inventory', '/stock-counts', '/purchases', '/suppliers'] as $path) {
            $this->actingAs($owner, 'tenant')->get($path)->assertForbidden();
        }
    }

    public function test_a_sale_takes_the_goods_off_the_shelf(): void
    {
        $owner = $this->owner();
        $this->location();
        $item = $this->stockedItem();

        app(StockService::class)->receive($item, 10, 5);

        $this->actingAs($owner, 'tenant')
            ->post('/transfers', [
                'customer' => 'أبو أحمد',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                'items' => [[
                    'itemId' => $item->id,
                    'quantity' => 3,
                    'name' => $item->name,
                    'price' => (string) $item->price,
                ]],
            ])
            ->assertRedirect();

        $this->assertSame(7.0, app(StockService::class)->onHand($item->id));

        // The movement points back at the order, which is what makes a recall
        // answerable later.
        $sale = StockMovement::where('type', StockMovementType::Sale)->sole();
        $this->assertSame('transfer', $sale->reference_type);
        $this->assertSame(Transfer::sole()->id, $sale->reference_id);
    }

    public function test_a_service_sale_touches_no_ledger(): void
    {
        $owner = $this->owner();
        $this->location();

        $service = MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'kind' => 'service',
            'track_stock' => false,
        ]);

        $this->actingAs($owner, 'tenant')
            ->post('/transfers', [
                'customer' => 'عميل',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                'items' => [[
                    'itemId' => $service->id,
                    'quantity' => 1,
                    'name' => $service->name,
                    'price' => (string) $service->price,
                ]],
            ])
            ->assertRedirect();

        $this->assertSame(0, StockMovement::count());
    }

    public function test_the_batches_pages_render_and_write_off(): void
    {
        $owner = $this->owner();
        $this->location();
        $item = $this->stockedItem();

        $batch = app(BatchService::class)->findOrCreate($item, 'LOT-9', now()->subDay()->toDateString());
        app(StockService::class)->receive($item, 6, 2, StockMovementType::Purchase, null, [
            'batch_id' => $batch->id,
            'batch_number' => 'LOT-9',
            'expires_at' => $batch->expires_at,
        ]);

        $this->actingAs($owner, 'tenant')->get('/batches')->assertOk()->assertSee('LOT-9');
        $this->actingAs($owner, 'tenant')->get("/batches/{$batch->id}")->assertOk();

        $this->actingAs($owner, 'tenant')
            ->post("/batches/{$batch->id}/write-off", ['quantity' => '6', 'reason' => 'انتهاء الصلاحية'])
            ->assertRedirect();

        $this->assertSame(0.0, $batch->onHand());
    }

    public function test_the_batches_pages_are_closed_to_a_vertical_without_lots(): void
    {
        $this->bindCurrentLicense(BusinessType::Bookstore);

        $this->actingAs($this->owner(), 'tenant')->get('/batches')->assertForbidden();
    }

    public function test_guests_are_redirected(): void
    {
        $this->get('/inventory')->assertRedirect('/login');
    }
}
