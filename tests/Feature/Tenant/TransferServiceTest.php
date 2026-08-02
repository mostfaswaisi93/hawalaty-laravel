<?php

namespace Tests\Feature\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use App\Models\Tenant\Account;
use App\Models\Tenant\Customer;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\TransferService;
use Illuminate\Support\Facades\Notification;
use Tests\TenantTestCase;

class TransferServiceTest extends TenantTestCase
{
    public function test_amount_is_computed_from_menu_item_prices_not_client_input(): void
    {
        Notification::fake();

        $item = MenuItem::factory()->create(['name' => 'شاورما دجاج', 'price' => '30.00']);

        $transfer = app(TransferService::class)->create([
            'customer' => 'أحمد محمد',
            'paymentMethod' => PaymentMethod::Cash,
            'items' => [

                ['itemId' => $item->id, 'quantity' => 3, 'name' => 'مجاناً', 'price' => '0.01'],
            ],
        ]);

        $this->assertSame('90.00', $transfer->amount);
        $this->assertSame(TransferStatus::Pending, $transfer->status);
        $this->assertSame('شاورما دجاج', $transfer->lineItems->first()->name);
        $this->assertSame('30.000', $transfer->lineItems->first()->price);
    }

    public function test_amount_sums_multiple_line_items(): void
    {
        Notification::fake();

        $itemA = MenuItem::factory()->create(['price' => '10.00']);
        $itemB = MenuItem::factory()->create(['price' => '15.50']);

        $transfer = app(TransferService::class)->create([
            'customer' => 'سارة علي',
            'paymentMethod' => PaymentMethod::Cash,
            'items' => [
                ['itemId' => $itemA->id, 'quantity' => 2],
                ['itemId' => $itemB->id, 'quantity' => 1],
            ],
        ]);

        $this->assertSame('35.50', $transfer->amount);
    }

    public function test_creating_a_transfer_reuses_an_existing_customer_by_phone(): void
    {
        Notification::fake();

        $existing = Customer::factory()->create(['name' => 'محمد خالد', 'phone' => '0599123456']);
        $item = MenuItem::factory()->create();

        $transfer = app(TransferService::class)->create([
            'customer' => 'محمد خالد الأحمد',
            'customerPhone' => '0599123456',
            'paymentMethod' => PaymentMethod::Cash,
            'items' => [['itemId' => $item->id, 'quantity' => 1]],
        ]);

        $this->assertSame($existing->id, $transfer->customer_id);
        $this->assertSame(1, Customer::count());
    }

    public function test_a_transfer_without_a_phone_creates_no_customer(): void
    {
        Notification::fake();

        $item = MenuItem::factory()->create();

        $transfer = app(TransferService::class)->create([
            'customer' => 'زبون عابر',
            'paymentMethod' => PaymentMethod::Cash,
            'items' => [['itemId' => $item->id, 'quantity' => 1]],
        ]);

        $this->assertNull($transfer->customer_id);
        $this->assertSame('زبون عابر', $transfer->customer);
        $this->assertSame(0, Customer::count());
    }

    public function test_bank_transfer_records_the_selected_bank(): void
    {
        Notification::fake();

        $bank = Account::factory()->create();
        $item = MenuItem::factory()->create();

        $transfer = app(TransferService::class)->create([
            'customer' => 'ليان أحمد',
            'paymentMethod' => PaymentMethod::Bank,
            'accountId' => $bank->id,
            'items' => [['itemId' => $item->id, 'quantity' => 1]],
        ]);

        $this->assertSame($bank->id, $transfer->account_id);
    }

    public function test_transaction_rollback_isolates_state_between_tests_a(): void
    {
        $this->assertSame(0, Customer::count());
        Customer::factory()->create();
        $this->assertSame(1, Customer::count());
    }

    public function test_transaction_rollback_isolates_state_between_tests_b(): void
    {
        $this->assertSame(0, Customer::count());
    }
}
