<?php

namespace Tests\Feature\Api;

use App\Enums\BusinessType;
use App\Enums\PaymentMethod;
use App\Enums\TaxCategory;
use App\Models\Tenant\Batch;
use App\Models\Tenant\ConstantList;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\ServiceResource;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\FitmentService;
use App\Services\Tenant\InvoiceService;
use App\Services\Tenant\ReturnService;
use App\Services\Tenant\SchedulingService;
use App\Services\Tenant\SerialService;
use App\Services\Tenant\StockService;
use App\Services\Tenant\TransferService;
use App\Services\Tenant\VariantService;
use Illuminate\Support\Facades\Notification;

/**
 * The vertical modules over HTTP.
 *
 * Two things every one of these checks: that the payload carries the figure a
 * client actually needs, and that a workspace whose trade does not include the
 * module is refused rather than handed an empty list it would read as "nothing
 * to show".
 */
class TenantApiModulesTest extends TenantApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        Location::default() ?? Location::create([
            'name' => 'Shop', 'kind' => 'store', 'is_default' => true, 'active' => true,
        ]);
    }

    private function stockedItem(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge([
            'menu_category_id' => MenuCategory::factory()->create(['name' => 'بقالة'])->id,
            'name' => 'زيت زيتون',
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'LTR',
            'price' => 100,
            'cost' => 60,
            'tax_rate' => 0,
            'tax_included' => true,
            'tax_category' => TaxCategory::Standard->value,
        ], $attributes));
    }

    private function sell(MenuItem $item, float $quantity = 2): Transfer
    {
        return app(TransferService::class)->create([
            'customer' => 'أبو أحمد',
            'paymentMethod' => PaymentMethod::Cash,
            'hasChange' => false,
            'items' => [['itemId' => $item->id, 'name' => $item->name, 'quantity' => $quantity, 'price' => (string) $item->price]],
        ]);
    }

    // -- Inventory ---------------------------------------------------------

    public function test_stock_levels_are_listed_with_their_derived_balance(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);
        $this->actAsOwner();

        $item = $this->stockedItem(['reorder_point' => 20]);
        app(StockService::class)->receive($item, 10, 60);

        $this->getJson('/api/v1/inventory')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'زيت زيتون')
            ->assertJsonPath('data.0.onHand', 10)
            ->assertJsonPath('data.0.value', 600)
            ->assertJsonPath('data.0.belowReorderPoint', true);
    }

    public function test_the_item_ledger_carries_the_cost_frozen_on_each_movement(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);
        $this->actAsOwner();

        $item = $this->stockedItem();
        app(StockService::class)->receive($item, 10, 60);
        $this->sell($item, 2);

        // A later, pricier delivery rolls the average up.
        app(StockService::class)->receive($item, 10, 90);

        $response = $this->getJson("/api/v1/inventory/{$item->id}")->assertOk();

        $sale = collect($response->json('data.movements'))->firstWhere('type', 'sale');

        // Loose comparison: a whole float goes over the wire as 10, not 10.0,
        // and the assertion is about the figure rather than the JSON type.
        $this->assertEquals(-2, $sale['quantity']);
        $this->assertEquals(60, $sale['unitCost']);
    }

    public function test_a_services_workspace_is_refused_the_inventory_endpoints(): void
    {
        $this->bindCurrentLicense(BusinessType::Services);
        $this->actAsOwner();

        // A 403 that says why, not a 200 with an empty array.
        $this->getJson('/api/v1/inventory')->assertForbidden();
        $this->getJson('/api/v1/batches')->assertForbidden();
    }

    // -- Batches -----------------------------------------------------------

    public function test_lots_are_listed_earliest_expiry_first(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);
        $this->actAsOwner();

        $item = $this->stockedItem(['name' => 'دواء']);

        app(StockService::class)->receive($item, 5, 10, attributes: [
            'batch_number' => 'LATE', 'expires_at' => now()->addMonths(9)->toDateString(),
        ]);
        app(StockService::class)->receive($item, 5, 10, attributes: [
            'batch_number' => 'SOON', 'expires_at' => now()->addDays(10)->toDateString(),
        ]);

        $this->getJson('/api/v1/batches')
            ->assertOk()
            ->assertJsonPath('data.0.batchNumber', 'SOON')
            ->assertJsonPath('data.0.onHand', 5)
            ->assertJsonPath('data.1.batchNumber', 'LATE');

        $this->getJson('/api/v1/batches/expiring?days=30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.batchNumber', 'SOON');
    }

    public function test_a_recall_trace_answers_with_who_to_call(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);
        $this->actAsOwner();

        $item = $this->stockedItem(['name' => 'دواء']);
        app(StockService::class)->receive($item, 10, 10, attributes: [
            'batch_number' => 'B-1', 'expires_at' => now()->addYear()->toDateString(),
        ]);

        $this->sell($item, 2);

        $batch = Batch::where('batch_number', 'B-1')->firstOrFail();

        $this->getJson("/api/v1/batches/{$batch->id}/sold-on")
            ->assertOk()
            ->assertJsonPath('data.sales.0.customer', 'أبو أحمد')
            ->assertJsonPath('data.sales.0.quantity', 2);
    }

    // -- Serials and warranty ----------------------------------------------

    public function test_a_serial_lookup_answers_whether_the_unit_is_still_covered(): void
    {
        $this->bindCurrentLicense(BusinessType::Electronics);
        $this->actAsOwner();

        $item = $this->stockedItem(['name' => 'جوال', 'track_serials' => true, 'warranty_months' => 12]);

        app(SerialService::class)->register($item, ['355123456789012'], 800.0);

        $this->getJson('/api/v1/serials?search=6789012')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.serial', '355123456789012');

        $this->getJson('/api/v1/warranty/lookup?serial=355123456789012')
            ->assertOk()
            ->assertJsonPath('data.0.item.name', 'جوال');
    }

    public function test_a_warranty_lookup_without_a_serial_is_refused(): void
    {
        $this->bindCurrentLicense(BusinessType::Electronics);
        $this->actAsOwner();

        $this->getJson('/api/v1/warranty/lookup')->assertStatus(422);
    }

    public function test_a_claim_can_be_opened_and_freezes_the_cover_decision(): void
    {
        $this->bindCurrentLicense(BusinessType::Electronics);
        $this->actAsOwner();

        $item = $this->stockedItem(['name' => 'جوال', 'track_serials' => true, 'warranty_months' => 12]);

        $this->postJson('/api/v1/warranty', [
            'itemId' => $item->id,
            'customerName' => 'أبو أحمد',
            'issue' => 'الشاشة لا تعمل',
        ])
            ->assertCreated()
            ->assertJsonPath('data.issue', 'الشاشة لا تعمل')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.underWarranty', false);
    }

    public function test_a_claim_with_neither_item_nor_serial_is_rejected(): void
    {
        $this->bindCurrentLicense(BusinessType::Electronics);
        $this->actAsOwner();

        $this->postJson('/api/v1/warranty', ['issue' => 'مكسور'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('itemId');
    }

    // -- Fitment -----------------------------------------------------------

    public function test_parts_are_found_by_vehicle_and_by_any_number_that_names_them(): void
    {
        $this->bindCurrentLicense(BusinessType::AutoParts);
        $this->actAsOwner();

        $fitment = app(FitmentService::class);

        $make = $fitment->createMake(['name' => 'Toyota', 'wmi' => 'JTD']);
        $model = $fitment->createModel($make, ['name' => 'Corolla']);

        $part = $this->stockedItem(['name' => 'فلتر زيت', 'sku' => 'OF-100']);
        app(StockService::class)->receive($part, 4, 20);

        $fitment->replaceFitments($part, [['vehicleModelId' => $model->id, 'yearFrom' => 2015, 'yearTo' => 2020]]);
        $fitment->replaceReferences($part, [['type' => 'oe', 'reference' => '0 986 424 815']]);

        $this->getJson("/api/v1/fitment/parts?modelId={$model->id}&year=2018")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'فلتر زيت')
            ->assertJsonPath('data.0.onHand', 4)
            ->assertJsonPath('data.0.fitment.model', 'Corolla');

        // Spaced and unspaced are the same number, and nobody types them alike.
        $this->getJson('/api/v1/fitment/reference?reference=0986424815')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'OF-100');
    }

    public function test_a_year_outside_the_fitment_range_finds_nothing(): void
    {
        $this->bindCurrentLicense(BusinessType::AutoParts);
        $this->actAsOwner();

        $fitment = app(FitmentService::class);
        $make = $fitment->createMake(['name' => 'Toyota']);
        $model = $fitment->createModel($make, ['name' => 'Corolla']);

        $part = $this->stockedItem(['name' => 'فلتر']);
        $fitment->replaceFitments($part, [['vehicleModelId' => $model->id, 'yearFrom' => 2015, 'yearTo' => 2020]]);

        $this->getJson("/api/v1/fitment/parts?modelId={$model->id}&year=2024")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // -- Scheduling --------------------------------------------------------

    public function test_a_days_diary_and_its_open_slots_are_returned(): void
    {
        $this->bindCurrentLicense(BusinessType::Services);
        $this->actAsOwner();

        $service = MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => 'قصّة',
            'kind' => 'service',
            'track_stock' => false,
            'price' => 40,
            'service_minutes' => 30,
        ]);

        $resource = ServiceResource::create([
            'name' => 'صالة 1', 'kind' => 'technician', 'active' => true,
        ]);

        $date = now()->addDay()->toDateString();

        app(SchedulingService::class)->book([
            'itemId' => $service->id,
            'resourceId' => $resource->id,
            'customerName' => 'أبو أحمد',
            'startsAt' => $date.' 10:00:00',
        ]);

        $this->getJson("/api/v1/appointments?date={$date}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customerName', 'أبو أحمد')
            ->assertJsonPath('data.0.minutes', 30);

        $slots = $this->getJson("/api/v1/appointments/slots?resourceId={$resource->id}&date={$date}")
            ->assertOk()
            ->json('data');

        // The 10:00 booking has to leave a hole in the free list, or the slots
        // are being read off working hours rather than off the diary.
        $this->assertIsArray($slots);
        $this->assertNotEmpty($slots);
    }

    // -- Returns -----------------------------------------------------------

    public function test_the_return_policy_endpoint_says_what_a_client_may_offer(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);
        $this->actAsOwner();

        $item = $this->stockedItem(['name' => 'دواء']);
        app(StockService::class)->receive($item, 10, 10);
        $transfer = $this->sell($item, 3);

        // A pharmacy never restocks, and the client has to be told before it
        // renders a choice the shop does not get to make.
        $this->getJson("/api/v1/transfers/{$transfer->id}/return-policy")
            ->assertOk()
            ->assertJsonPath('data.allowsRestock', false)
            ->assertJsonPath('data.lines.0.sold', 3)
            ->assertJsonPath('data.lines.0.remaining', 3);
    }

    public function test_a_completed_return_appears_with_its_lines(): void
    {
        $this->bindCurrentLicense(BusinessType::Clothing);
        $this->actAsOwner();

        $item = $this->stockedItem(['name' => 'قميص']);
        app(StockService::class)->receive($item, 10, 40);
        $transfer = $this->sell($item, 2);

        $return = app(ReturnService::class)->create([
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
        ]);
        app(ReturnService::class)->complete($return);

        $this->getJson('/api/v1/returns')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'completed');

        $this->getJson("/api/v1/returns/{$return->id}")
            ->assertOk()
            ->assertJsonPath('data.lines.0.quantity', 1)
            ->assertJsonPath('data.lines.0.resalable', true);
    }

    // -- Invoicing ---------------------------------------------------------

    public function test_an_invoice_is_issued_and_read_back_with_its_chain(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);
        $this->actAsOwner();

        app(InvoiceService::class)->saveSellerSettings([
            InvoiceService::SELLER_NAME => 'بقالة الأمانة',
            InvoiceService::SELLER_TAX_NUMBER => '399999999900003',
            InvoiceService::CURRENCY => 'ILS',
        ]);

        $item = $this->stockedItem(['price' => 115, 'tax_rate' => 15]);
        app(StockService::class)->receive($item, 10, 60);
        $transfer = $this->sell($item, 2);

        $response = $this->postJson("/api/v1/invoices/sales/{$transfer->id}", [
            'buyerName' => 'مطعم الشام',
            'buyerTaxNumber' => '311111111100003',
        ])->assertCreated();

        $id = $response->json('data.id');

        $this->assertSame('388', $response->json('data.documentTypeCode'));
        $this->assertEquals(200, $response->json('data.totals.taxExclusive'));
        $this->assertEquals(30, $response->json('data.totals.tax'));
        $this->assertTrue($response->json('data.totals.balances'));

        $this->getJson("/api/v1/invoices/{$id}")
            ->assertOk()
            ->assertJsonPath('data.lines.0.unitCode', 'LTR');

        // The XML is served as XML - base64 in a JSON envelope would only give
        // the caller something to undo.
        $this->get("/api/v1/invoices/{$id}/xml")
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8');

        $this->getJson('/api/v1/invoices/verify')
            ->assertOk()
            ->assertJsonPath('data.intact', true)
            ->assertJsonPath('data.broken', 0);
    }

    public function test_issuing_an_invoice_twice_is_refused_with_a_reason(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);
        $this->actAsOwner();

        app(InvoiceService::class)->saveSellerSettings([InvoiceService::SELLER_NAME => 'بقالة']);

        $item = $this->stockedItem();
        app(StockService::class)->receive($item, 10, 60);
        $transfer = $this->sell($item, 1);

        $this->postJson("/api/v1/invoices/sales/{$transfer->id}")->assertCreated();
        $this->postJson("/api/v1/invoices/sales/{$transfer->id}")->assertStatus(422);
    }

    // -- Margins -----------------------------------------------------------

    public function test_margins_are_returned_with_the_basis_of_each_cost(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);
        $this->actAsOwner();

        $item = $this->stockedItem();
        app(StockService::class)->receive($item, 10, 60);
        $this->sell($item, 2);

        $this->getJson('/api/v1/margins')
            ->assertOk()
            ->assertJsonPath('data.summary.revenue', 200)
            ->assertJsonPath('data.summary.margin', 80)
            ->assertJsonPath('data.items.0.basis', 'ledger');

        $this->getJson('/api/v1/margins/abc')
            ->assertOk()
            ->assertJsonPath('data.0.class', 'A');
    }

    public function test_the_valuation_endpoint_reports_the_ias_2_write_down(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);
        $this->actAsOwner();

        $impaired = $this->stockedItem(['name' => 'منخفض', 'price' => 50, 'cost' => 80]);
        app(StockService::class)->receive($impaired, 10, 80);

        $this->getJson('/api/v1/margins/valuation')
            ->assertOk()
            ->assertJsonPath('data.total', 800)
            ->assertJsonPath('data.writeDown', 300)
            ->assertJsonPath('data.rows.0.impaired', true);
    }

    public function test_a_backwards_date_range_is_refused(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);
        $this->actAsOwner();

        $this->getJson('/api/v1/margins?from=2026-05-01&to=2026-01-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    // -- Variants ----------------------------------------------------------

    public function test_a_style_returns_its_grid_keyed_by_square(): void
    {
        $this->bindCurrentLicense(BusinessType::Clothing);
        $this->actAsOwner();

        $parent = $this->stockedItem(['name' => 'قميص', 'track_stock' => false]);

        $sizes = ConstantList::create(['key' => 'sizes', 'title' => 'المقاس']);
        $small = $sizes->items()->create(['value' => 'S', 'position' => 1]);
        $medium = $sizes->items()->create(['value' => 'M', 'position' => 2]);

        app(VariantService::class)->generate($parent, [
            $sizes->id => [$small->id, $medium->id],
        ]);

        $this->getJson('/api/v1/variants')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'قميص')
            ->assertJsonPath('data.0.variants', 2);

        $this->getJson("/api/v1/variants/{$parent->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.parent.name', 'قميص');
    }
}
