<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Enums\SerialStatus;
use App\Enums\StockMovementType;
use App\Models\Tenant\Account;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SaleReturn;
use App\Models\Tenant\SerialNumber;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\ReturnService;
use App\Services\Tenant\SerialService;
use App\Services\Tenant\StockService;
use App\Services\Tenant\TransferService;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TenantTestCase;

class ReturnServiceTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindCurrentLicense(BusinessType::Clothing);
        Notification::fake();

        Location::default() ?? Location::create([
            'name' => 'Shop', 'kind' => 'store', 'is_default' => true, 'active' => true,
        ]);
    }

    private function returns(): ReturnService
    {
        return app(ReturnService::class);
    }

    private function item(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => 'قميص',
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'H87',
            'price' => 80,
            'cost' => 50,
        ], $attributes));
    }

    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    /**
     * A real sale, through the ordinary path - a return has to reverse something
     * that actually happened.
     */
    private function sell(MenuItem $item, float $quantity = 2): Transfer
    {
        app(StockService::class)->receive($item, 10, (float) $item->cost);

        return app(TransferService::class)->create([
            'customer' => 'أبو أحمد',
            'paymentMethod' => 'cash',
            'hasChange' => false,
            'items' => [[
                'itemId' => $item->id,
                'name' => $item->name,
                'quantity' => $quantity,
                'price' => (string) $item->price,
            ]],
        ]);
    }

    // -- The fixed rule ----------------------------------------------------

    public function test_the_original_sale_is_never_touched(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 2);

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
        ]);

        $this->returns()->complete($return);

        // Reversing by deletion would make yesterday's takings depend on when
        // you looked at them.
        $this->assertNotNull(Transfer::find($transfer->id));
        $this->assertSame('160.00', $transfer->fresh()->amount);
        $this->assertSame(1, $transfer->fresh()->lineItems()->count());
    }

    public function test_nothing_moves_until_the_return_is_completed(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 2);

        $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 2]],
        ]);

        // 10 received, 2 sold.
        $this->assertSame(8.0, app(StockService::class)->onHand($item->id));
    }

    // -- Restock rules by vertical ----------------------------------------

    public function test_a_resalable_return_goes_back_on_the_shelf(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 2);

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 2, 'condition' => 'resalable']],
        ]);

        $this->returns()->complete($return);

        $this->assertSame(10.0, app(StockService::class)->onHand($item->id));
        $this->assertSame(1, StockMovement::where('type', StockMovementType::ReturnIn)->count());
        $this->assertSame(0, StockMovement::where('type', StockMovementType::Waste)->count());
    }

    public function test_a_damaged_return_comes_back_and_then_leaves_again(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 2);

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 2, 'condition' => 'damaged']],
        ]);

        $this->returns()->complete($return);

        // Two movements rather than none: the ledger says the goods returned and
        // then were written off, instead of forgetting the customer handed
        // something over.
        $this->assertSame(8.0, app(StockService::class)->onHand($item->id));
        $this->assertSame(1, StockMovement::where('type', StockMovementType::ReturnIn)->count());
        $this->assertSame(1, StockMovement::where('type', StockMovementType::Waste)->count());
    }

    /**
     * The rule the shop does not get to override.
     */
    public function test_food_is_never_restocked_whatever_the_cashier_picks(): void
    {
        $this->bindCurrentLicense(BusinessType::Bakery);

        $item = $this->item(['name' => 'كعك']);
        $transfer = $this->sell($item, 2);

        $return = app(ReturnService::class)->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'cash',
            // Asked for resalable; the trade says otherwise.
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 2, 'condition' => 'resalable']],
        ]);

        $this->assertSame('damaged', $return->lines()->sole()->condition);

        app(ReturnService::class)->complete($return);

        $this->assertSame(8.0, app(StockService::class)->onHand($item->id));
    }

    public function test_medicine_is_never_restocked_either(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);

        $this->assertFalse(app(ReturnService::class)->allowsRestock());
        $this->assertTrue(app(ReturnService::class)->allowsRestock() === false);
    }

    public function test_a_clothing_shop_does_restock(): void
    {
        $this->assertTrue($this->returns()->allowsRestock());
        $this->assertSame(14, $this->returns()->windowDays());
    }

    // -- Partial returns ---------------------------------------------------

    public function test_two_partial_returns_cannot_exceed_what_was_bought(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 3);
        $line = $transfer->lineItems->first();

        $first = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $line->id, 'quantity' => 2]],
        ]);

        $this->returns()->complete($first);

        $this->expectException(RuntimeException::class);

        $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $line->id, 'quantity' => 2]],
        ]);
    }

    public function test_the_remaining_quantity_accounts_for_earlier_returns(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 3);
        $line = $transfer->lineItems->first();

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $line->id, 'quantity' => 1]],
        ]);

        $this->returns()->complete($return);

        $row = $this->returns()->returnableLines($transfer->fresh())->sole();

        $this->assertSame(3.0, $row['sold']);
        $this->assertSame(1.0, $row['returned']);
        $this->assertSame(2.0, $row['remaining']);
    }

    // -- Money -------------------------------------------------------------

    public function test_a_restocking_fee_comes_off_the_refund(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 2);

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'cash',
            'restockingFee' => 10,
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 2]],
        ]);

        $this->assertSame('160.00', $return->subtotal);
        $this->assertSame('150.00', $return->refund_total);
    }

    public function test_a_fee_cannot_exceed_the_goods(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 1);

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'cash',
            'restockingFee' => 500,
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
        ]);

        // Never a negative refund - the shop cannot end up owed money by
        // handing goods back.
        $this->assertSame('80.00', $return->restocking_fee);
        $this->assertSame('0.00', $return->refund_total);
    }

    public function test_store_credit_lands_on_the_customer(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 2);

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'customerPhone' => '0599000111',
            'refundMethod' => 'store_credit',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 2]],
        ]);

        $this->returns()->complete($return);

        $this->assertSame('160.00', Customer::find($return->fresh()->customer_id)->store_credit);
    }

    public function test_a_bank_refund_credits_the_account(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 1);

        $account = Account::factory()->create(['balance' => 1000]);

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'bank',
            'accountId' => $account->id,
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
        ]);

        $this->returns()->complete($return);

        // Money out is a credit, exactly as for any other payment.
        $this->assertSame('920.00', $account->fresh()->balance);
    }

    public function test_a_bank_refund_without_an_account_is_refused(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 1);

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'bank',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
        ]);

        $this->expectException(RuntimeException::class);

        $this->returns()->complete($return);
    }

    // -- Serials -----------------------------------------------------------

    public function test_a_returned_unit_is_marked_returned_not_in_stock(): void
    {
        $this->bindCurrentLicense(BusinessType::Electronics);

        $phone = $this->item(['name' => 'هاتف', 'track_serials' => true, 'warranty_months' => 12]);

        app(StockService::class)->receive($phone, 1, 900);
        app(SerialService::class)->register($phone, ['IMEI-1'], 900, Location::default()->id);

        $units = app(SerialService::class)->reserve($phone, ['IMEI-1']);

        $transfer = app(TransferService::class)->create([
            'customer' => 'عميل',
            'paymentMethod' => 'cash',
            'hasChange' => false,
            'items' => [[
                'itemId' => $phone->id, 'name' => $phone->name, 'quantity' => 1,
                'price' => (string) $phone->price, 'serials' => ['IMEI-1'],
            ]],
        ]);

        $return = app(ReturnService::class)->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'cash',
            'lines' => [[
                'transferLineItemId' => $transfer->lineItems->first()->id,
                'quantity' => 1,
                'condition' => 'resalable',
                'serialId' => $units->first()->id,
            ]],
        ]);

        app(ReturnService::class)->complete($return);

        // Back on the books, but as a returned unit - someone has to look at it
        // before it is sold again.
        $this->assertSame(SerialStatus::Returned, SerialNumber::sole()->status);
        $this->assertSame(1.0, app(StockService::class)->onHand($phone->id));
    }

    public function test_a_damaged_returned_unit_is_scrapped(): void
    {
        $this->bindCurrentLicense(BusinessType::Electronics);

        $phone = $this->item(['name' => 'هاتف', 'track_serials' => true]);

        app(StockService::class)->receive($phone, 1, 900);
        app(SerialService::class)->register($phone, ['IMEI-2'], 900, Location::default()->id);

        $units = app(SerialService::class)->reserve($phone, ['IMEI-2']);

        $transfer = app(TransferService::class)->create([
            'customer' => 'عميل',
            'paymentMethod' => 'cash',
            'hasChange' => false,
            'items' => [[
                'itemId' => $phone->id, 'name' => $phone->name, 'quantity' => 1,
                'price' => (string) $phone->price, 'serials' => ['IMEI-2'],
            ]],
        ]);

        $return = app(ReturnService::class)->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'cash',
            'lines' => [[
                'transferLineItemId' => $transfer->lineItems->first()->id,
                'quantity' => 1,
                'condition' => 'damaged',
                'serialId' => $units->first()->id,
            ]],
        ]);

        app(ReturnService::class)->complete($return);

        $this->assertSame(SerialStatus::Scrapped, SerialNumber::sole()->status);
        $this->assertSame(0.0, app(StockService::class)->onHand($phone->id));
    }

    // -- Lifecycle ---------------------------------------------------------

    public function test_a_completed_return_cannot_be_completed_again(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 1);

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
        ]);

        $this->returns()->complete($return);

        $this->expectException(RuntimeException::class);

        $this->returns()->complete($return->fresh());
    }

    public function test_a_rejected_return_moves_nothing(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 2);

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'عميل',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 2]],
        ]);

        $this->returns()->reject($return, 'خارج نافذة الإرجاع');

        $this->assertSame('rejected', $return->fresh()->status);
        $this->assertSame(8.0, app(StockService::class)->onHand($item->id));
    }

    public function test_an_empty_return_cannot_be_completed(): void
    {
        $return = SaleReturn::create([
            'customer_name' => 'عميل',
            'status' => 'draft',
            'refund_method' => 'cash',
        ]);

        $this->expectException(RuntimeException::class);

        $this->returns()->complete($return);
    }

    // -- Window ------------------------------------------------------------

    public function test_the_window_is_measured_from_the_sale(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 1);

        $this->assertTrue($this->returns()->windowFor($transfer)['withinWindow']);

        // Set directly: created_at is not mass-assignable, so update() drops it.
        $transfer->forceFill(['created_at' => now()->subDays(30)])->save();

        // Reported, not enforced: refusing outright would stop a shop recording
        // a goodwill return it decided to accept anyway.
        $window = $this->returns()->windowFor($transfer->fresh());

        $this->assertSame(30, $window['days']);
        $this->assertFalse($window['withinWindow']);
    }

    // -- Screens -----------------------------------------------------------

    public function test_the_screens_render(): void
    {
        $owner = $this->owner();
        $item = $this->item();
        $transfer = $this->sell($item, 2);

        $return = $this->returns()->create([
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
        ]);

        $this->actingAs($owner, 'tenant')->get('/returns')->assertOk()->assertSee('أبو أحمد');
        $this->actingAs($owner, 'tenant')->get('/returns/create')->assertOk();
        $this->actingAs($owner, 'tenant')->get('/returns/create?transferId='.$transfer->id)->assertOk()->assertSee('قميص');
        $this->actingAs($owner, 'tenant')->get("/returns/{$return->id}")->assertOk();
    }

    public function test_returns_are_available_in_every_vertical(): void
    {
        $owner = $this->owner();

        // Not gated on a capability: every trade takes goods back. What differs
        // is whether they can be resold.
        foreach ([BusinessType::Grocery, BusinessType::Pharmacy, BusinessType::Services] as $type) {
            $this->bindCurrentLicense($type);

            $this->actingAs($owner, 'tenant')->get('/returns')->assertOk();
        }
    }
}
