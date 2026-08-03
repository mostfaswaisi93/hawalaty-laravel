<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Enums\SerialStatus;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\SaleReturn;
use App\Models\Tenant\SerialNumber;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Models\Tenant\WarrantyClaim;
use App\Services\Tenant\StockService;
use App\Services\Tenant\SyncService;
use App\Services\Tenant\TransferService;
use Illuminate\Support\Facades\Notification;
use Tests\TenantTestCase;

/**
 * What a disconnected device can know, and what it is allowed to have done.
 */
class OfflineCoverageTest extends TenantTestCase
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

    private function sync(): SyncService
    {
        return app(SyncService::class);
    }

    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    private function event(string $uuid, string $type, string $op, array $payload): array
    {
        return ['clientUuid' => $uuid, 'entityType' => $type, 'operation' => $op, 'payload' => $payload];
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
            'tax_category' => 'S',
        ], $attributes));
    }

    private function sell(MenuItem $item, float $quantity = 2): Transfer
    {
        app(StockService::class)->receive($item, 50, (float) $item->cost);

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

    // -- What the device may have done --------------------------------------

    /**
     * A return arrives as a draft: nothing moves and no money is refunded until
     * someone completes it here, where the vertical's restock rules apply.
     */
    public function test_a_return_queued_offline_lands_as_a_draft(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 2);
        $before = app(StockService::class)->onHand($item->id);

        $result = $this->sync()->push(null, [
            $this->event('r-1', 'sale_return', 'create', [
                'transferId' => $transfer->id,
                'customerName' => 'أبو أحمد',
                'refundMethod' => 'cash',
                'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
            ]),
        ]);

        $this->assertSame(['r-1'], $result['applied']);
        $this->assertSame([], $result['failed']);

        $return = SaleReturn::firstOrFail();
        $this->assertTrue($return->isDraft());

        // Nothing moved. Completing is the online step.
        $this->assertSame($before, app(StockService::class)->onHand($item->id));
    }

    public function test_a_warranty_claim_queued_offline_is_opened(): void
    {
        $this->bindCurrentLicense(BusinessType::Electronics);

        $item = $this->item(['name' => 'جوال', 'track_serials' => true, 'warranty_months' => 12]);

        $result = $this->sync()->push(null, [
            $this->event('w-1', 'warranty_claim', 'create', [
                'itemId' => $item->id,
                'customerName' => 'سامي',
                'issue' => 'الشاشة لا تعمل',
            ]),
        ]);

        $this->assertSame(['w-1'], $result['applied']);
        $this->assertSame(1, WarrantyClaim::count());
        $this->assertSame('الشاشة لا تعمل', WarrantyClaim::first()->issue);
    }

    /**
     * Gapless numbering needs the sequence row locked at the moment the number
     * is taken. A device offline cannot hold that lock, and two devices each
     * inventing the next number produce a duplicate or a hole.
     */
    public function test_an_invoice_cannot_be_issued_offline(): void
    {
        $result = $this->sync()->push(null, [
            $this->event('i-1', 'invoice', 'create', ['transferId' => 'whatever']),
        ]);

        $this->assertSame([], $result['applied']);
        $this->assertCount(1, $result['failed']);

        // Resolved through the catalogue, not asserted as literal Arabic - the
        // suite runs in English and the refusal is translated like anything else.
        $this->assertSame(
            __('لا تُصدَر الفواتير دون اتصال — الترقيم المتسلسل يحتاج قفلًا على الخادم.'),
            $result['failed'][0]['error'],
        );
    }

    public function test_a_device_payload_is_validated_like_any_other_request(): void
    {
        $result = $this->sync()->push(null, [
            // No lines, so nothing to return.
            $this->event('r-bad', 'sale_return', 'create', [
                'customerName' => 'أبو أحمد',
                'refundMethod' => 'cash',
                'lines' => [],
            ]),
        ]);

        $this->assertSame([], $result['applied']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame(0, SaleReturn::count());
    }

    public function test_the_same_queued_return_is_never_applied_twice(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 2);

        $event = $this->event('r-dup', 'sale_return', 'create', [
            'transferId' => $transfer->id,
            'customerName' => 'أبو أحمد',
            'refundMethod' => 'cash',
            'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
        ]);

        $this->sync()->push(null, [$event]);
        $second = $this->sync()->push(null, [$event]);

        $this->assertSame(['r-dup'], $second['alreadyApplied']);
        $this->assertSame(1, SaleReturn::count());
    }

    // -- The push contract ---------------------------------------------------

    public function test_the_endpoint_accepts_the_new_types(): void
    {
        $item = $this->item();
        $transfer = $this->sell($item, 2);

        $this->actingAs($this->owner(), 'tenant')
            ->postJson('/sync/push', [
                'events' => [$this->event('r-http', 'sale_return', 'create', [
                    'transferId' => $transfer->id,
                    'customerName' => 'أبو أحمد',
                    'refundMethod' => 'cash',
                    'lines' => [['transferLineItemId' => $transfer->lineItems->first()->id, 'quantity' => 1]],
                ])],
            ])
            ->assertOk();

        $this->assertSame(1, SaleReturn::count());
    }

    /**
     * A queue on the other end has to be able to read the refusal. The web
     * mounting of this endpoint is called by the browser client with fetch, and
     * a redirect carrying an HTML page would look like a rejection with no
     * stated reason - so the events get retried forever.
     */
    public function test_a_rejected_batch_comes_back_as_json_on_the_web_route_too(): void
    {
        $response = $this->actingAs($this->owner(), 'tenant')
            ->postJson('/sync/push', [
                'events' => [$this->event('x-1', 'stock_movement', 'create', ['a' => 1])],
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('events.0.entityType');
    }

    /**
     * A draft exists to be reviewed. Amending or deleting it from a replayed
     * event would skip the review.
     */
    public function test_a_device_may_create_a_return_but_not_amend_one(): void
    {
        $this->actingAs($this->owner(), 'tenant')
            ->postJson('/sync/push', [
                'events' => [$this->event('r-upd', 'sale_return', 'update', ['id' => 'x'])],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('events.0.operation');
    }

    public function test_a_serialised_sale_still_names_its_units_offline(): void
    {
        $this->bindCurrentLicense(BusinessType::Electronics);

        $item = $this->item(['name' => 'جوال', 'track_serials' => true, 'price' => 900, 'cost' => 700]);
        app(StockService::class)->receive($item, 2, 700);

        SerialNumber::create([
            'menu_item_id' => $item->id,
            'serial' => 'IMEI-1',
            'status' => SerialStatus::InStock,
            'location_id' => Location::default()->id,
        ]);

        $result = $this->sync()->push(null, [
            $this->event('s-1', 'transfer', 'create', [
                'customer' => 'سامي',
                'paymentMethod' => 'cash',
                'hasChange' => false,
                'items' => [[
                    'itemId' => $item->id,
                    'name' => $item->name,
                    'quantity' => 1,
                    'price' => 900,
                    'serials' => ['IMEI-1'],
                ]],
            ]),
        ]);

        $this->assertSame(['s-1'], $result['applied']);
        $this->assertSame(SerialStatus::Sold, SerialNumber::where('serial', 'IMEI-1')->first()->status);
    }
}
