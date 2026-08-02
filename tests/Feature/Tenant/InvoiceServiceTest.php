<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Enums\InvoiceType;
use App\Enums\TaxCategory;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\InvoiceService;
use App\Services\Tenant\ReturnService;
use App\Services\Tenant\StockService;
use App\Services\Tenant\TransferService;
use App\Services\Tenant\UblRenderer;
use App\Support\InvoiceQr;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TenantTestCase;

class InvoiceServiceTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindCurrentLicense(BusinessType::Grocery);
        Notification::fake();

        Location::default() ?? Location::create([
            'name' => 'Shop', 'kind' => 'store', 'is_default' => true, 'active' => true,
        ]);

        $this->invoices()->saveSellerSettings([
            InvoiceService::SELLER_NAME => 'بقالة الأمانة',
            InvoiceService::SELLER_TAX_NUMBER => '399999999900003',
            InvoiceService::SELLER_ADDRESS => 'غزة - شارع الوحدة',
            InvoiceService::CURRENCY => 'ILS',
        ]);
    }

    private function invoices(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function item(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge([
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
        ], $attributes));
    }

    /**
     * @param  array<int, array{item: MenuItem, quantity: float}>  $lines
     */
    private function sell(array $lines, float $discount = 0): Transfer
    {
        $items = [];

        foreach ($lines as $line) {
            app(StockService::class)->receive($line['item'], 100, (float) $line['item']->cost);

            $items[] = [
                'itemId' => $line['item']->id,
                'name' => $line['item']->name,
                'quantity' => $line['quantity'],
                'price' => (string) $line['item']->price,
            ];
        }

        return app(TransferService::class)->create([
            'customer' => 'أبو أحمد',
            'paymentMethod' => 'cash',
            'hasChange' => false,
            'discount' => $discount,
            'items' => $items,
        ]);
    }

    // -- Numbering ---------------------------------------------------------

    public function test_numbering_runs_without_gaps_and_restarts_per_series(): void
    {
        $item = $this->item();

        $first = $this->invoices()->issueForSale($this->sell([['item' => $item, 'quantity' => 1]]));
        $second = $this->invoices()->issueForSale($this->sell([['item' => $item, 'quantity' => 1]]));
        $third = $this->invoices()->issueForSale($this->sell([['item' => $item, 'quantity' => 1]]));

        $this->assertSame([1, 2, 3], [$first->sequence, $second->sequence, $third->sequence]);
        $this->assertSame('INV-'.now()->format('Y').'-000001', $first->number);

        // A credit note is its own series: mixing the two would leave holes in
        // whichever of them an auditor reads on its own.
        $this->assertStringStartsWith('INV-', $first->series);
    }

    public function test_a_sale_cannot_be_invoiced_twice(): void
    {
        $transfer = $this->sell([['item' => $this->item(), 'quantity' => 1]]);

        $this->invoices()->issueForSale($transfer);

        $this->expectException(RuntimeException::class);
        $this->invoices()->issueForSale($transfer);
    }

    public function test_nothing_is_issued_before_the_seller_is_identified(): void
    {
        $this->invoices()->saveSellerSettings([InvoiceService::SELLER_NAME => null]);

        $this->expectException(RuntimeException::class);
        $this->invoices()->issueForSale($this->sell([['item' => $this->item(), 'quantity' => 1]]));
    }

    // -- Tax arithmetic ----------------------------------------------------

    public function test_tax_is_extracted_from_a_shelf_price_that_already_includes_it(): void
    {
        $invoice = $this->invoices()->issueForSale(
            $this->sell([['item' => $this->item(), 'quantity' => 2]])
        );

        // 2 x 115 inclusive of 15% = 230 gross, 200 net, 30 tax.
        $this->assertSame('200.00', $invoice->line_extension_amount);
        $this->assertSame('30.00', $invoice->tax_amount);
        $this->assertSame('230.00', $invoice->payable_amount);
        $this->assertTrue($invoice->totalsBalance());
    }

    public function test_tax_is_added_on_top_of_a_price_quoted_without_it(): void
    {
        $item = $this->item(['price' => 100, 'tax_included' => false]);

        $invoice = $this->invoices()->issueForSale($this->sell([['item' => $item, 'quantity' => 1]]));

        $this->assertSame('100.00', $invoice->line_extension_amount);
        $this->assertSame('15.00', $invoice->tax_amount);
        $this->assertSame('115.00', $invoice->payable_amount);
    }

    /**
     * The whole reason the category is stored separately from the rate: both of
     * these charge nothing, and they are not the same supply.
     */
    public function test_an_exempt_item_carries_its_reason_and_never_charges(): void
    {
        $item = $this->item([
            'name' => 'خبز',
            'price' => 5,
            'tax_rate' => 15,
            'tax_category' => TaxCategory::Exempt->value,
        ]);

        $invoice = $this->invoices()->issueForSale($this->sell([['item' => $item, 'quantity' => 4]]));

        $this->assertSame('0.00', $invoice->tax_amount);
        $this->assertSame('20.00', $invoice->payable_amount);
        $this->assertSame('0.00', $invoice->lines->first()->tax_rate);
        $this->assertSame(TaxCategory::Exempt->value, $invoice->lines->first()->tax_category);

        $this->assertStringContainsString('TaxExemptionReason', (string) $invoice->xml);
    }

    public function test_the_breakdown_groups_by_category_and_rate_not_by_line(): void
    {
        $standard = $this->item(['name' => 'زيت', 'price' => 115, 'tax_rate' => 15]);
        $alsoStandard = $this->item(['name' => 'سكر', 'price' => 23, 'tax_rate' => 15]);
        $zero = $this->item(['name' => 'حليب', 'price' => 10, 'tax_rate' => 0, 'tax_category' => TaxCategory::ZeroRated->value]);

        $invoice = $this->invoices()->issueForSale($this->sell([
            ['item' => $standard, 'quantity' => 1],
            ['item' => $alsoStandard, 'quantity' => 1],
            ['item' => $zero, 'quantity' => 1],
        ]));

        $breakdown = $invoice->taxBreakdown();

        $this->assertCount(2, $breakdown);
        $this->assertSame(120.0, $breakdown->firstWhere('category', 'S')['taxable']);
        $this->assertSame(18.0, $breakdown->firstWhere('category', 'S')['tax']);
        $this->assertSame(0.0, $breakdown->firstWhere('category', 'Z')['tax']);
    }

    /**
     * A discount that reduces what was paid but not what was taxed overstates
     * the tax due, so it has to land inside the lines.
     */
    public function test_a_document_discount_is_spread_across_the_lines(): void
    {
        $invoice = $this->invoices()->issueForSale(
            $this->sell([['item' => $this->item(), 'quantity' => 2]], discount: 30)
        );

        // 230 charged, 30 off = 200 gross, of which 173.91 net and 26.09 tax.
        $this->assertSame('200.00', $invoice->payable_amount);
        $this->assertSame('173.91', $invoice->tax_exclusive_amount);
        $this->assertSame('26.09', $invoice->tax_amount);
        $this->assertSame('0.00', $invoice->allowance_total);
        $this->assertTrue($invoice->totalsBalance());
    }

    /**
     * Allocating a discount line by line rounds each one on its own, and the
     * parts can miss the whole. The invoice still has to say what the customer
     * actually handed over.
     */
    public function test_the_payable_total_matches_what_the_till_took(): void
    {
        $a = $this->item(['name' => 'أ', 'price' => 3.33]);
        $b = $this->item(['name' => 'ب', 'price' => 3.33]);
        $c = $this->item(['name' => 'ج', 'price' => 3.34]);

        $transfer = $this->sell([
            ['item' => $a, 'quantity' => 1],
            ['item' => $b, 'quantity' => 1],
            ['item' => $c, 'quantity' => 1],
        ], discount: 1);

        $invoice = $this->invoices()->issueForSale($transfer);

        // Transfer::amount is already net of the discount.
        $this->assertSame((float) $transfer->amount, (float) $invoice->payable_amount);

        // And the parts still add to the whole.
        $this->assertSame(
            (float) $invoice->payable_amount,
            round($invoice->lines->sum(fn ($line) => $line->grossAmount()), 2),
        );
    }

    public function test_every_line_reconciles_net_plus_tax_to_its_total(): void
    {
        $invoice = $this->invoices()->issueForSale(
            $this->sell([['item' => $this->item(['price' => 7.77]), 'quantity' => 3]])
        );

        foreach ($invoice->lines as $line) {
            $this->assertSame(
                $line->grossAmount(),
                round((float) $line->line_extension_amount + (float) $line->tax_amount, 2),
            );
        }
    }

    // -- Document type -----------------------------------------------------

    public function test_a_buyer_with_a_tax_number_gets_a_full_invoice_not_a_simplified_one(): void
    {
        $anonymous = $this->invoices()->issueForSale($this->sell([['item' => $this->item(), 'quantity' => 1]]));
        $this->assertSame(InvoiceType::Simplified, $anonymous->type);

        $business = $this->invoices()->issueForSale(
            $this->sell([['item' => $this->item(), 'quantity' => 1]]),
            ['name' => 'مطعم الشام', 'taxNumber' => '311111111100003'],
        );

        $this->assertSame(InvoiceType::Standard, $business->type);
        $this->assertSame('388', $business->type->documentTypeCode());
    }

    public function test_a_completed_return_produces_a_credit_note_and_a_draft_does_not(): void
    {
        $item = $this->item();
        $transfer = $this->sell([['item' => $item, 'quantity' => 2]]);
        $this->invoices()->issueForSale($transfer);

        $return = app(ReturnService::class)->create([
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
        ]);

        try {
            $this->invoices()->issueForReturn($return);
            $this->fail('A draft return must not be creditable.');
        } catch (RuntimeException) {
            // Expected: nothing has moved yet, so there is nothing to credit.
        }

        app(ReturnService::class)->complete($return);

        $note = $this->invoices()->issueForReturn($return->fresh());

        $this->assertSame(InvoiceType::CreditNote, $note->type);
        $this->assertSame('381', $note->type->documentTypeCode());
        $this->assertStringStartsWith('CN-', $note->number);
        $this->assertSame(1, $note->sequence);
    }

    public function test_credit_notes_count_against_the_declared_tax(): void
    {
        $item = $this->item();
        $transfer = $this->sell([['item' => $item, 'quantity' => 2]]);
        $this->invoices()->issueForSale($transfer);

        $return = app(ReturnService::class)->create([
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 2]],
        ]);
        app(ReturnService::class)->complete($return);
        $this->invoices()->issueForReturn($return->fresh());

        $summary = $this->invoices()->summary();

        $this->assertSame(2, $summary['count']);
        $this->assertSame(0.0, $summary['tax']);
        $this->assertSame(0.0, $summary['gross']);
    }

    // -- The chain ---------------------------------------------------------

    public function test_the_first_document_starts_a_chain_and_the_rest_follow_it(): void
    {
        $item = $this->item();

        $first = $this->invoices()->issueForSale($this->sell([['item' => $item, 'quantity' => 1]]));
        $second = $this->invoices()->issueForSale($this->sell([['item' => $item, 'quantity' => 1]]));

        $this->assertNull($first->previous_hash);
        $this->assertSame($first->hash, $second->previous_hash);
        $this->assertNotSame($first->hash, $second->hash);

        $this->assertTrue($this->invoices()->verifyChain()->every(fn (array $row) => $row['valid']));
    }

    public function test_editing_an_issued_document_breaks_the_chain(): void
    {
        $item = $this->item();

        $first = $this->invoices()->issueForSale($this->sell([['item' => $item, 'quantity' => 1]]));
        $this->invoices()->issueForSale($this->sell([['item' => $item, 'quantity' => 1]]));

        // Exactly the tampering the chain exists to catch: someone reaching past
        // the application and changing a stored document.
        Invoice::where('id', $first->id)->update(['xml' => str_replace('115.00', '11.50', (string) $first->xml)]);

        $results = $this->invoices()->verifyChain();

        $this->assertFalse($results->first()['valid']);
        $this->assertSame(1, $results->reject(fn (array $row) => $row['valid'])->count());
    }

    // -- The document itself -----------------------------------------------

    public function test_the_rendered_document_is_well_formed_ubl(): void
    {
        $invoice = $this->invoices()->issueForSale(
            $this->sell([['item' => $this->item(), 'quantity' => 2]]),
            ['name' => 'مطعم & أولاده', 'taxNumber' => '311111111100003'],
        );

        $xml = new \DOMDocument;

        // An ampersand in a buyer's name is where hand-written XML breaks.
        $this->assertTrue($xml->loadXML((string) $invoice->xml));
        $this->assertSame('Invoice', $xml->documentElement->localName);

        $text = (string) $invoice->xml;

        $this->assertStringContainsString('urn:cen.eu:en16931:2017', $text);
        $this->assertStringContainsString('<cbc:InvoiceTypeCode>388</cbc:InvoiceTypeCode>', $text);
        $this->assertStringContainsString('unitCode="LTR"', $text);
        $this->assertStringContainsString('currencyID="ILS"', $text);
        $this->assertStringContainsString('مطعم &amp; أولاده', $text);
    }

    public function test_a_credit_note_renders_as_a_credit_note_document(): void
    {
        $item = $this->item();
        $transfer = $this->sell([['item' => $item, 'quantity' => 2]]);

        $return = app(ReturnService::class)->create([
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
        ]);
        app(ReturnService::class)->complete($return);

        $note = $this->invoices()->issueForReturn($return->fresh());

        $xml = new \DOMDocument;
        $this->assertTrue($xml->loadXML((string) $note->xml));
        $this->assertSame('CreditNote', $xml->documentElement->localName);

        $this->assertStringContainsString('<cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>', (string) $note->xml);
        $this->assertStringContainsString('<cbc:CreditedQuantity', (string) $note->xml);
    }

    public function test_the_seller_identity_is_frozen_at_issue(): void
    {
        $invoice = $this->invoices()->issueForSale($this->sell([['item' => $this->item(), 'quantity' => 1]]));

        $this->invoices()->saveSellerSettings([InvoiceService::SELLER_NAME => 'اسم آخر تمامًا']);

        // Renaming the shop must not rewrite what was already sent out.
        $this->assertSame('بقالة الأمانة', $invoice->fresh()->seller_name);
    }

    public function test_the_qr_payload_decodes_back_to_what_the_invoice_says(): void
    {
        $invoice = $this->invoices()->issueForSale($this->sell([['item' => $this->item(), 'quantity' => 2]]));

        $fields = InvoiceQr::decode((string) $invoice->qr_payload);

        $this->assertSame('بقالة الأمانة', $fields[1]);
        $this->assertSame('399999999900003', $fields[2]);
        $this->assertSame('230.00', $fields[4]);
        $this->assertSame('30.00', $fields[5]);
    }

    public function test_the_renderer_is_a_pure_function_of_the_stored_document(): void
    {
        $invoice = $this->invoices()->issueForSale($this->sell([['item' => $this->item(), 'quantity' => 2]]));

        // Rendering twice has to give the same bytes, or the hash proves nothing.
        $this->assertSame((string) $invoice->xml, app(UblRenderer::class)->render($invoice->fresh('lines')));
    }

    public function test_an_empty_sale_produces_no_document(): void
    {
        $transfer = Transfer::factory()->create(['customer' => 'لا شيء', 'amount' => 0]);

        $this->expectException(RuntimeException::class);
        $this->invoices()->issueForSale($transfer);
    }

    public function test_the_issuer_is_recorded(): void
    {
        $roles = $this->seedStandardRoles();
        $member = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $this->actingAs($member, 'tenant');

        $invoice = $this->invoices()->issueForSale($this->sell([['item' => $this->item(), 'quantity' => 1]]));

        $this->assertSame($member->id, $invoice->team_member_id);
    }
}
