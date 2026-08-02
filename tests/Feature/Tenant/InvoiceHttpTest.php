<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Enums\TaxCategory;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\TenantSetting;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\InvoiceService;
use App\Services\Tenant\ReturnService;
use App\Services\Tenant\StockService;
use App\Services\Tenant\TransferService;
use Illuminate\Support\Facades\Notification;
use Tests\TenantTestCase;

class InvoiceHttpTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindCurrentLicense(BusinessType::Grocery);
        Notification::fake();

        Location::default() ?? Location::create([
            'name' => 'Shop', 'kind' => 'store', 'is_default' => true, 'active' => true,
        ]);
    }

    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    private function configure(): void
    {
        app(InvoiceService::class)->saveSellerSettings([
            InvoiceService::SELLER_NAME => 'بقالة الأمانة',
            InvoiceService::SELLER_TAX_NUMBER => '399999999900003',
            InvoiceService::CURRENCY => 'ILS',
        ]);
    }

    private function item(): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => 'زيت زيتون',
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'LTR',
            'price' => 115,
            'cost' => 70,
            'tax_rate' => 15,
            'tax_included' => true,
            'tax_category' => TaxCategory::Standard->value,
        ]);
    }

    private function sell(MenuItem $item, float $quantity = 2): Transfer
    {
        app(StockService::class)->receive($item, 100, (float) $item->cost);

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

    public function test_the_pages_render(): void
    {
        $this->configure();
        $owner = $this->owner();

        $invoice = app(InvoiceService::class)->issueForSale($this->sell($this->item()));

        $this->actingAs($owner, 'tenant')->get('/invoices')->assertOk()->assertSee($invoice->number);
        $this->actingAs($owner, 'tenant')->get('/invoices/settings')->assertOk()->assertSee('بقالة الأمانة');
        $this->actingAs($owner, 'tenant')->get('/invoices/verify')->assertOk();
        $this->actingAs($owner, 'tenant')->get("/invoices/{$invoice->id}")->assertOk()->assertSee($invoice->number);
    }

    /**
     * The static segments have to win over the wildcard, or the settings screen
     * resolves as an invoice whose id happens to be "settings".
     */
    public function test_the_settings_route_is_not_swallowed_by_the_wildcard(): void
    {
        $this->configure();

        $this->actingAs($this->owner(), 'tenant')
            ->get('/invoices/settings')
            ->assertOk()
            ->assertSee(__('بيانات البائع'));
    }

    public function test_the_index_warns_when_the_seller_is_not_set_up(): void
    {
        $this->actingAs($this->owner(), 'tenant')
            ->get('/invoices')
            ->assertOk()
            ->assertSee(__('لم تُضبط بيانات البائع بعد'));
    }

    public function test_seller_settings_can_be_saved(): void
    {
        $this->actingAs($this->owner(), 'tenant')
            ->put('/invoices/settings', [
                'sellerName' => 'محل النور',
                'sellerTaxNumber' => '300000000000003',
                'sellerAddress' => 'رفح',
                'currency' => 'ILS',
            ])
            ->assertRedirect('/invoices/settings');

        $this->assertSame('محل النور', TenantSetting::where('key', InvoiceService::SELLER_NAME)->value('value'));
    }

    public function test_a_seller_without_a_name_is_refused(): void
    {
        $this->actingAs($this->owner(), 'tenant')
            ->put('/invoices/settings', ['sellerName' => '', 'currency' => 'ILS'])
            ->assertSessionHasErrors('sellerName');
    }

    public function test_an_invoice_is_issued_from_the_sale_screen(): void
    {
        $this->configure();
        $transfer = $this->sell($this->item());

        $this->actingAs($this->owner(), 'tenant')
            ->post("/invoices/sales/{$transfer->id}", [
                'buyerName' => 'مطعم الشام',
                'buyerTaxNumber' => '311111111100003',
            ])
            ->assertRedirect();

        $invoice = Invoice::where('transfer_id', $transfer->id)->first();

        $this->assertNotNull($invoice);
        $this->assertSame('مطعم الشام', $invoice->buyer_name);
    }

    public function test_issuing_twice_reports_the_reason_rather_than_duplicating(): void
    {
        $this->configure();
        $transfer = $this->sell($this->item());
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')->post("/invoices/sales/{$transfer->id}")->assertRedirect();

        $this->actingAs($owner, 'tenant')
            ->post("/invoices/sales/{$transfer->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Invoice::where('transfer_id', $transfer->id)->count());
    }

    public function test_a_credit_note_is_issued_from_the_return_screen(): void
    {
        $this->configure();

        $item = $this->item();
        $transfer = $this->sell($item);
        app(InvoiceService::class)->issueForSale($transfer);

        $return = app(ReturnService::class)->create([
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
        ]);
        app(ReturnService::class)->complete($return);

        $this->actingAs($this->owner(), 'tenant')
            ->post("/invoices/returns/{$return->id}")
            ->assertRedirect();

        $this->assertSame(1, Invoice::where('sale_return_id', $return->id)->count());
    }

    public function test_the_ubl_document_downloads(): void
    {
        $this->configure();

        $invoice = app(InvoiceService::class)->issueForSale($this->sell($this->item()));

        $response = $this->actingAs($this->owner(), 'tenant')->get("/invoices/{$invoice->id}/xml");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $response->assertHeader('content-disposition', 'attachment; filename="'.$invoice->number.'.xml"');
        $this->assertStringContainsString($invoice->number, $response->getContent());
    }

    /**
     * Two different gates. Whoever rings up a sale issues its invoice; who the
     * business says it is on that invoice is a configuration decision, and a
     * cashier does not get to change it.
     */
    public function test_a_cashier_can_issue_but_cannot_change_who_the_seller_is(): void
    {
        $this->configure();

        $roles = $this->seedStandardRoles();
        $cashier = TeamMember::factory()->create(['role_id' => $roles['staff']->id]);

        $this->actingAs($cashier, 'tenant')->get('/invoices')->assertOk();
        $this->actingAs($cashier, 'tenant')->get('/invoices/settings')->assertRedirect('/dashboard');

        $this->actingAs($cashier, 'tenant')
            ->put('/invoices/settings', ['sellerName' => 'محل آخر', 'currency' => 'ILS'])
            ->assertForbidden();

        $this->assertSame('بقالة الأمانة', app(InvoiceService::class)->sellerSettings()[InvoiceService::SELLER_NAME]);
    }

    public function test_the_tax_fields_are_saved_from_the_catalogue_screen(): void
    {
        $category = MenuCategory::factory()->create();

        $this->actingAs($this->owner(), 'tenant')
            ->post('/menu/items', [
                'name' => 'خبز',
                'menuCategoryId' => $category->id,
                'price' => 5,
                'taxRate' => 0,
                'taxCategory' => TaxCategory::Exempt->value,
                'active' => 1,
            ])
            ->assertRedirect();

        $item = MenuItem::where('name', 'خبز')->firstOrFail();

        $this->assertSame(TaxCategory::Exempt->value, $item->tax_category);
        $this->assertSame('0.00', $item->tax_rate);
    }

    /**
     * A rate on its own cannot say whether a zero is zero-rated or exempt, so a
     * catalogue that never mentions the category still has to produce a valid
     * one rather than a blank.
     */
    public function test_a_category_is_inferred_when_none_is_given(): void
    {
        $category = MenuCategory::factory()->create();

        $this->actingAs($this->owner(), 'tenant')
            ->post('/menu/items', [
                'name' => 'سكر',
                'menuCategoryId' => $category->id,
                'price' => 10,
                'taxRate' => 15,
                'active' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(TaxCategory::Standard->value, MenuItem::where('name', 'سكر')->value('tax_category'));
    }
}
