<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\LedgerEntry;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\TransferService;
use Tests\TenantTestCase;

/**
 * Orders post to an ACCOUNT: the form picks one, the order stores
 * `account_id`, and the money lands on that account's ledger.
 */
class OrderAccountFlowTest extends TenantTestCase
{
    private function member(): TeamMember
    {
        return TeamMember::factory()->create(['role_id' => $this->seedStandardRoles()['owner']->id]);
    }

    private function menuItem(float $price = 50): MenuItem
    {
        $category = MenuCategory::factory()->create();

        return MenuItem::factory()->create([
            'menu_category_id' => $category->id,
            'price' => $price,
            'active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function orderPayload(MenuItem $item, array $overrides = []): array
    {
        return [
            'customer' => 'زبون تجريبي',
            'items' => [[
                'itemId' => $item->id,
                'name' => $item->name,
                'quantity' => 1,
                'price' => (float) $item->price,
            ]],
            ...$overrides,
        ];
    }

    public function test_a_bank_order_stores_the_account_and_debits_its_ledger(): void
    {
        $item = $this->menuItem(50);
        $account = Account::factory()->create();

        $transfer = app(TransferService::class)->create($this->orderPayload($item, [
            'paymentMethod' => 'bank',
            'accountId' => $account->id,
            'transferredAmount' => 50,
            'referenceNumber' => 'TRX-'.\Illuminate\Support\Str::random(10),
        ]));

        $this->assertSame($account->id, $transfer->account_id);

        // The account received money -> a DEBIT, and the balance rises.
        $entry = $account->ledgerEntries()->sole();
        $this->assertSame(50.0, (float) $entry->debit);
        $this->assertSame(0.0, (float) $entry->credit);
        $this->assertSame(50.0, (float) $entry->running_balance);
        $this->assertSame($transfer->id, $entry->transfer_id);
        $this->assertSame(50.0, (float) $account->fresh()->balance);
    }

    public function test_change_paid_out_of_an_account_is_credited(): void
    {
        $item = $this->menuItem(50);
        $account = Account::factory()->create();

        app(TransferService::class)->create($this->orderPayload($item, [
            'paymentMethod' => 'cash',
            'hasChange' => true,
            'changeMethod' => 'bank',
            'changeAccountId' => $account->id,
            'changeAmount' => 20,
        ]));

        $entry = $account->ledgerEntries()->sole();
        $this->assertSame(0.0, (float) $entry->debit);
        $this->assertSame(20.0, (float) $entry->credit);
        $this->assertSame(-20.0, (float) $account->fresh()->balance);
    }

    public function test_a_plain_cash_order_touches_no_ledger(): void
    {
        $item = $this->menuItem(50);

        app(TransferService::class)->create($this->orderPayload($item, [
            'paymentMethod' => 'cash',
            'hasChange' => false,
        ]));

        $this->assertSame(0, LedgerEntry::count());
    }

    /** A wallet account is the same transfer path, told apart by its `kind`. */
    public function test_a_wallet_order_posts_to_the_wallet_account(): void
    {
        $item = $this->menuItem(50);
        $wallet = Account::factory()->wallet()->create();

        $transfer = app(TransferService::class)->create($this->orderPayload($item, [
            'paymentMethod' => 'bank',
            'accountId' => $wallet->id,
            'transferredAmount' => 50,
            'referenceNumber' => 'TRX-'.\Illuminate\Support\Str::random(10),
        ]));

        $this->assertSame($wallet->id, $transfer->account_id);
        $this->assertSame('wallet', $transfer->account->kind->value);
        $this->assertSame(50.0, (float) $wallet->fresh()->balance);
    }

    // ─────────────── HTTP / validation ───────────────

    public function test_the_order_page_offers_the_accounts(): void
    {
        $account = Account::factory()->create(['holder_name' => 'صاحب الحساب التجريبي']);

        $this->actingAs($this->member(), 'tenant')->get('/transfers')
            ->assertOk()
            ->assertViewHas('accounts')
            ->assertSee('صاحب الحساب التجريبي');
    }

    public function test_a_bank_order_posted_from_the_form_is_created(): void
    {
        $item = $this->menuItem(50);
        $account = Account::factory()->create();

        $this->actingAs($this->member(), 'tenant')->post('/transfers', [
            'customer' => 'زبون',
            'items' => [['itemId' => $item->id, 'name' => $item->name, 'quantity' => 1, 'price' => 50]],
            'paymentMethod' => 'bank',
            'accountId' => $account->id,
            'transferredAmount' => 50,
            'referenceNumber' => 'TRX-'.\Illuminate\Support\Str::random(10),
        ])->assertRedirect(route('transfers.created', Transfer::firstOrFail()->id));

        $this->assertSame($account->id, Transfer::firstOrFail()->account_id);
    }

    /**
     * `amount` stays what the customer OWES, so every revenue figure keeps
     * working; the discount is stored beside it only so the invoice can show
     * the arithmetic.
     */
    public function test_a_discount_comes_off_the_amount_and_is_recorded(): void
    {
        $item = $this->menuItem(50);

        $transfer = app(TransferService::class)->create($this->orderPayload($item, [
            'paymentMethod' => 'cash',
            'hasChange' => false,
            'discount' => 12.5,
        ]));

        $this->assertSame(37.5, (float) $transfer->amount);
        $this->assertSame(12.5, (float) $transfer->discount);
    }

    /** A discount larger than the order would drive `amount` below the DB's CHECK. */
    public function test_a_discount_cannot_exceed_the_order_total(): void
    {
        $item = $this->menuItem(50);

        $transfer = app(TransferService::class)->create($this->orderPayload($item, [
            'paymentMethod' => 'cash',
            'hasChange' => false,
            'discount' => 999,
        ]));

        $this->assertSame(0.0, (float) $transfer->amount);
        $this->assertSame(50.0, (float) $transfer->discount);
    }

    public function test_an_order_without_a_discount_records_zero(): void
    {
        $item = $this->menuItem(50);

        $transfer = app(TransferService::class)->create($this->orderPayload($item, [
            'paymentMethod' => 'cash',
            'hasChange' => false,
        ]));

        $this->assertSame(50.0, (float) $transfer->amount);
        $this->assertSame(0.0, (float) $transfer->discount);
    }

    /**
     * The reference is what an order is reconciled against, so entering the
     * same one twice - the classic double-booking - has to be refused.
     */
    public function test_a_duplicate_reference_number_is_rejected(): void
    {
        $item = $this->menuItem(50);
        $account = Account::factory()->create();

        app(TransferService::class)->create($this->orderPayload($item, [
            'paymentMethod' => 'bank',
            'accountId' => $account->id,
            'transferredAmount' => 50,
            'referenceNumber' => 'TRX-DUPLICATE',
        ]));

        $this->actingAs($this->member(), 'tenant')->post('/transfers', [
            'customer' => 'زبون آخر',
            'items' => [['itemId' => $item->id, 'name' => $item->name, 'quantity' => 1, 'price' => 50]],
            'paymentMethod' => 'bank',
            'accountId' => $account->id,
            'transferredAmount' => 50,
            'referenceNumber' => 'TRX-DUPLICATE',
        ])->assertSessionHasErrors('referenceNumber');

        $this->assertSame(1, Transfer::count());
    }

    public function test_a_transfer_order_requires_a_reference_number(): void
    {
        $item = $this->menuItem(50);
        $account = Account::factory()->create();

        $this->actingAs($this->member(), 'tenant')->post('/transfers', [
            'customer' => 'زبون',
            'items' => [['itemId' => $item->id, 'name' => $item->name, 'quantity' => 1, 'price' => 50]],
            'paymentMethod' => 'bank',
            'accountId' => $account->id,
            'transferredAmount' => 50,
        ])->assertSessionHasErrors('referenceNumber');

        $this->assertSame(0, Transfer::count());
    }

    /** A cash order has no slip, so it needs no reference - and two may coexist. */
    public function test_cash_orders_need_no_reference_and_do_not_collide(): void
    {
        $item = $this->menuItem(50);

        app(TransferService::class)->create($this->orderPayload($item, ['paymentMethod' => 'cash', 'hasChange' => false]));
        app(TransferService::class)->create($this->orderPayload($item, ['paymentMethod' => 'cash', 'hasChange' => false]));

        $this->assertSame(2, Transfer::count());
    }

    public function test_a_transfer_order_without_an_account_is_rejected(): void
    {
        $item = $this->menuItem(50);

        $this->actingAs($this->member(), 'tenant')->post('/transfers', [
            'customer' => 'زبون',
            'items' => [['itemId' => $item->id, 'name' => $item->name, 'quantity' => 1, 'price' => 50]],
            'paymentMethod' => 'bank',
            'transferredAmount' => 50,
        ])->assertSessionHasErrors('accountId');

        $this->assertSame(0, Transfer::count());
    }

    public function test_change_to_bank_without_a_destination_is_rejected(): void
    {
        $item = $this->menuItem(50);

        $this->actingAs($this->member(), 'tenant')->post('/transfers', [
            'customer' => 'زبون',
            'items' => [['itemId' => $item->id, 'name' => $item->name, 'quantity' => 1, 'price' => 50]],
            'paymentMethod' => 'cash',
            'hasChange' => 1,
            'changeMethod' => 'bank',
            'changeAmount' => 10,
        ])->assertSessionHasErrors('changeAccountId');
    }

    /**
     * The offline-sync path validates with rules() ALONE, so the destination
     * requirement has to live there rather than in withValidator().
     */
    public function test_the_destination_guard_applies_to_the_sync_rules(): void
    {
        $rules = (new \App\Http\Requests\Tenant\Transfer\StoreTransferRequest)->rules();

        $validator = \Illuminate\Support\Facades\Validator::make([
            'customer' => 'زبون',
            'items' => [['itemId' => $this->menuItem()->id, 'name' => 'x', 'quantity' => 1, 'price' => 1]],
            'paymentMethod' => 'bank',
            'transferredAmount' => 10,
        ], $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('accountId', $validator->errors()->toArray());
    }
}
