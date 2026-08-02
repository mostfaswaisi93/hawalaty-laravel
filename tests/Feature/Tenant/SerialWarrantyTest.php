<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Enums\SerialStatus;
use App\Enums\StockMovementType;
use App\Enums\TransferStatus;
use App\Enums\WarrantyClaimStatus;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SerialNumber;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\WarrantyClaim;
use App\Services\Tenant\SerialService;
use App\Services\Tenant\StockService;
use App\Services\Tenant\WarrantyService;
use RuntimeException;
use Tests\TenantTestCase;

class SerialWarrantyTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindCurrentLicense(BusinessType::Electronics);
    }

    private function serials(): SerialService
    {
        return app(SerialService::class);
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    private function phone(int $warrantyMonths = 12): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => 'هاتف',
            'kind' => 'goods',
            'track_stock' => true,
            'track_serials' => true,
            'warranty_months' => $warrantyMonths,
            'unit_code' => 'H87',
            'price' => 1200,
            'cost' => 900,
        ]);
    }

    private function location(): Location
    {
        return Location::default() ?? Location::create([
            'name' => 'Shop', 'kind' => 'store', 'is_default' => true, 'active' => true,
        ]);
    }

    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    /**
     * Bring units in the way a receipt does: quantity to the ledger, identities
     * alongside it.
     */
    private function receive(MenuItem $item, array $serials, float $cost = 900): void
    {
        $this->stock()->receive($item, count($serials), $cost);
        $this->serials()->register($item, $serials, $cost, Location::default()?->id);
    }

    /**
     * A real order row - serial_numbers.transfer_id is a foreign key, so a made
     * up id would only prove the constraint works.
     */
    private function order(): Transfer
    {
        return Transfer::create([
            'customer' => 'عميل',
            'status' => TransferStatus::Completed,
            'amount' => 1200,
            'payment_method' => 'cash',
        ]);
    }

    // -- Registration ------------------------------------------------------

    public function test_units_are_registered_in_stock(): void
    {
        $this->location();
        $phone = $this->phone();

        $this->receive($phone, ['IMEI-1', 'IMEI-2']);

        $this->assertSame(2, SerialNumber::forItem($phone->id)->available()->count());
        $this->assertSame(2.0, $this->stock()->onHand($phone->id));
    }

    public function test_a_duplicate_number_for_the_same_item_is_refused(): void
    {
        $this->location();
        $phone = $this->phone();

        $this->receive($phone, ['IMEI-1']);

        $this->expectException(RuntimeException::class);

        $this->serials()->register($phone, ['IMEI-1'], 900);
    }

    public function test_registering_nothing_is_refused(): void
    {
        $this->location();
        $phone = $this->phone();

        $this->expectException(RuntimeException::class);

        $this->serials()->register($phone, [], 900);
    }

    // -- The ledger refuses anonymity --------------------------------------

    public function test_a_serialised_item_cannot_leave_without_naming_the_unit(): void
    {
        $this->location();
        $phone = $this->phone();

        $this->receive($phone, ['IMEI-1', 'IMEI-2']);

        // Which handset went out is the whole reason the item is serialised.
        $this->expectException(RuntimeException::class);

        $this->stock()->issue($phone, 1);
    }

    public function test_issuing_writes_one_movement_per_unit(): void
    {
        $this->location();
        $phone = $this->phone();

        $this->receive($phone, ['IMEI-1', 'IMEI-2', 'IMEI-3']);

        $units = $this->serials()->reserve($phone, ['IMEI-1', 'IMEI-3']);
        $movements = $this->stock()->issueSerials($phone, $units);

        // Three handsets sold is three facts, not one row of quantity three.
        $this->assertCount(2, $movements);
        $this->assertSame(['IMEI-1', 'IMEI-3'], $movements->pluck('serial_number')->sort()->values()->all());
        $this->assertSame(1.0, $this->stock()->onHand($phone->id));
    }

    public function test_a_unit_already_sold_cannot_be_sold_again(): void
    {
        $this->location();
        $phone = $this->phone();

        $this->receive($phone, ['IMEI-1']);

        $units = $this->serials()->reserve($phone, ['IMEI-1']);
        $this->serials()->markSold($units, $phone, $this->order()->id);

        $this->expectException(RuntimeException::class);

        $this->serials()->reserve($phone, ['IMEI-1']);
    }

    public function test_an_unknown_number_is_refused(): void
    {
        $this->location();
        $phone = $this->phone();

        $this->receive($phone, ['IMEI-1']);

        $this->expectException(RuntimeException::class);

        $this->serials()->reserve($phone, ['NOT-OURS']);
    }

    // -- Warranty ----------------------------------------------------------

    public function test_the_warranty_clock_starts_at_the_sale_and_is_frozen(): void
    {
        $this->location();
        $phone = $this->phone(warrantyMonths: 12);

        $this->receive($phone, ['IMEI-1']);

        $units = $this->serials()->reserve($phone, ['IMEI-1']);
        $this->serials()->markSold($units, $phone, $this->order()->id);

        $unit = SerialNumber::sole();

        $this->assertTrue($unit->underWarranty());
        $this->assertSame(now()->addMonths(12)->toDateString(), $unit->warranty_ends_at->toDateString());

        // Shortening the catalogue term must not shorten what was promised.
        $phone->update(['warranty_months' => 3]);

        $this->assertSame(12, $unit->fresh()->warranty_months);
    }

    public function test_no_term_means_no_warranty_rather_than_an_expired_one(): void
    {
        $this->location();
        $phone = $this->phone(warrantyMonths: 0);

        $this->receive($phone, ['IMEI-1']);

        $units = $this->serials()->reserve($phone, ['IMEI-1']);
        $this->serials()->markSold($units, $phone, $this->order()->id);

        $unit = SerialNumber::sole();

        // "No warranty" and "warranty expired" read differently at a counter.
        $this->assertFalse($unit->hasWarranty());
        $this->assertFalse($unit->underWarranty());
    }

    public function test_a_claim_takes_its_cover_from_the_unit(): void
    {
        $this->location();
        $phone = $this->phone(warrantyMonths: 12);

        $this->receive($phone, ['IMEI-1']);
        $units = $this->serials()->reserve($phone, ['IMEI-1']);
        $this->serials()->markSold($units, $phone, $this->order()->id);

        $claim = app(WarrantyService::class)->open([
            'serialId' => SerialNumber::sole()->id,
            'customerName' => 'أبو أحمد',
            'issue' => 'الشاشة لا تعمل',
        ]);

        $this->assertTrue($claim->under_warranty);
        $this->assertSame($phone->id, $claim->menu_item_id);
    }

    public function test_an_expired_unit_produces_an_uncovered_claim(): void
    {
        $this->location();
        $phone = $this->phone(warrantyMonths: 12);

        $this->receive($phone, ['IMEI-1']);
        $units = $this->serials()->reserve($phone, ['IMEI-1']);
        $this->serials()->markSold($units, $phone, $this->order()->id);

        SerialNumber::sole()->update(['warranty_ends_at' => now()->subDay()->toDateString()]);

        $claim = app(WarrantyService::class)->open([
            'serialId' => SerialNumber::sole()->id,
            'customerName' => 'عميل',
            'issue' => 'عطل',
        ]);

        $this->assertFalse($claim->under_warranty);
    }

    public function test_what_the_shop_absorbed_is_the_gap_between_cost_and_charge(): void
    {
        $phone = $this->phone();

        $claim = WarrantyClaim::create([
            'menu_item_id' => $phone->id,
            'customer_name' => 'عميل',
            'issue' => 'عطل',
            'status' => WarrantyClaimStatus::Open,
            'repair_cost' => 200,
            'charged_amount' => 50,
            'reported_at' => now(),
        ]);

        $this->assertSame(150.0, $claim->absorbedCost());
        $this->assertSame(150.0, app(WarrantyService::class)->summary()['absorbed']);
    }

    public function test_closing_a_claim_stamps_when(): void
    {
        $phone = $this->phone();

        $claim = WarrantyClaim::create([
            'menu_item_id' => $phone->id,
            'customer_name' => 'عميل',
            'issue' => 'عطل',
            'status' => WarrantyClaimStatus::Open,
            'reported_at' => now(),
        ]);

        $this->assertNull($claim->resolved_at);

        app(WarrantyService::class)->update($claim, ['status' => WarrantyClaimStatus::InRepair->value]);
        $this->assertNull($claim->fresh()->resolved_at);

        app(WarrantyService::class)->update($claim->fresh(), ['status' => WarrantyClaimStatus::Resolved->value]);
        $this->assertNotNull($claim->fresh()->resolved_at);
    }

    // -- Reconciliation ----------------------------------------------------

    public function test_a_ledger_that_disagrees_with_the_units_is_surfaced(): void
    {
        $this->location();
        $phone = $this->phone();

        // Quantity received without its numbers - an import, a manual entry.
        $this->stock()->receive($phone, 5, 900);
        $this->serials()->register($phone, ['IMEI-1', 'IMEI-2'], 900);

        $mismatch = $this->serials()->mismatches($this->stock())->sole();

        $this->assertSame(5.0, $mismatch['ledger']);
        $this->assertSame(2, $mismatch['serials']);
    }

    public function test_scrapping_a_unit_removes_it_from_both_books(): void
    {
        $this->location();
        $phone = $this->phone();

        $this->receive($phone, ['IMEI-1', 'IMEI-2']);

        $unit = SerialNumber::where('serial', 'IMEI-1')->sole();

        $this->stock()->scrapSerial($unit, 'تلف أثناء العرض');
        $this->serials()->scrap($unit, 'تلف أثناء العرض');

        $this->assertSame(SerialStatus::Scrapped, $unit->fresh()->status);
        $this->assertSame(1.0, $this->stock()->onHand($phone->id));
        $this->assertSame(StockMovementType::Waste, StockMovement::where('serial_id', $unit->id)->sole()->type);
    }

    // -- The sale ----------------------------------------------------------

    public function test_the_till_records_which_units_left_and_starts_their_cover(): void
    {
        $this->location();
        $owner = $this->owner();
        $phone = $this->phone(warrantyMonths: 24);

        $this->receive($phone, ['IMEI-1', 'IMEI-2', 'IMEI-3']);

        $this->actingAs($owner, 'tenant')
            ->post('/transfers', [
                'customer' => 'أبو أحمد',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                'items' => [[
                    'itemId' => $phone->id,
                    'quantity' => 2,
                    'name' => $phone->name,
                    'price' => (string) $phone->price,
                    'serials' => ['IMEI-1', 'IMEI-2'],
                ]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $transfer = Transfer::sole();

        $sold = SerialNumber::whereIn('serial', ['IMEI-1', 'IMEI-2'])->get();

        foreach ($sold as $unit) {
            $this->assertSame(SerialStatus::Sold, $unit->status);
            $this->assertSame($transfer->id, $unit->transfer_id);
            $this->assertTrue($unit->underWarranty());
        }

        $this->assertSame(SerialStatus::InStock, SerialNumber::where('serial', 'IMEI-3')->sole()->status);
        $this->assertSame(1.0, $this->stock()->onHand($phone->id));
    }

    public function test_the_till_refuses_a_serialised_sale_with_no_units_named(): void
    {
        $this->location();
        $owner = $this->owner();
        $phone = $this->phone();

        $this->receive($phone, ['IMEI-1']);

        $this->actingAs($owner, 'tenant')
            ->post('/transfers', [
                'customer' => 'عميل',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                'items' => [['itemId' => $phone->id, 'quantity' => 1, 'name' => $phone->name, 'price' => '1200']],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Transfer::count());
        $this->assertSame(SerialStatus::InStock, SerialNumber::sole()->status);
    }

    // -- Screens -----------------------------------------------------------

    public function test_the_screens_render(): void
    {
        $this->location();
        $owner = $this->owner();
        $phone = $this->phone();

        $this->receive($phone, ['IMEI-1']);

        $unit = SerialNumber::sole();

        $this->actingAs($owner, 'tenant')->get('/serials')->assertOk()->assertSee('IMEI-1');
        $this->actingAs($owner, 'tenant')->get("/serials/{$unit->id}")->assertOk();
        $this->actingAs($owner, 'tenant')->get('/warranty')->assertOk();
        $this->actingAs($owner, 'tenant')->get('/warranty/lookup?serial=IMEI-1')->assertOk()->assertSee('IMEI-1');
    }

    public function test_the_screens_are_closed_to_a_vertical_without_them(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);

        $owner = $this->owner();

        $this->assertFalse(app(SerialService::class)->enabled());
        $this->actingAs($owner, 'tenant')->get('/serials')->assertForbidden();
        $this->actingAs($owner, 'tenant')->get('/warranty')->assertForbidden();
    }
}
