<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\CustomerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Tests\TenantTestCase;

/**
 * Regression coverage for CustomerService: trimmed exact-name matching,
 * phone backfill-without-overwrite, the unique-constraint race fallback,
 * and the list()/find() aggregates.
 */
class CustomerServiceTest extends TenantTestCase
{
    private function service(): CustomerService
    {
        return app(CustomerService::class);
    }

    public function test_find_or_create_trims_the_name_before_matching_and_creating(): void
    {
        $customer = $this->service()->findOrCreate('  محمد  ', '0599111111');

        $this->assertSame('محمد', $customer->name);
        $this->assertSame(1, Customer::count());
    }

    public function test_find_or_create_reuses_an_existing_customer_by_exact_trimmed_name(): void
    {
        $existing = Customer::factory()->create(['name' => 'محمد خالد']);

        $customer = $this->service()->findOrCreate('  محمد خالد  ', '0599222222');

        $this->assertSame($existing->id, $customer->id);
        $this->assertSame(1, Customer::count());
    }

    public function test_find_or_create_creates_a_new_customer_when_name_is_new(): void
    {
        $customer = $this->service()->findOrCreate('سارة علي', '0599333333');

        $this->assertTrue($customer->wasRecentlyCreated);
        $this->assertSame('سارة علي', $customer->name);
        $this->assertSame('0599333333', $customer->phone);
        $this->assertSame(1, Customer::count());
    }

    public function test_find_or_create_backfills_phone_when_existing_customer_has_none(): void
    {
        $existing = Customer::factory()->create(['name' => 'ليان أحمد', 'phone' => null]);

        $customer = $this->service()->findOrCreate('ليان أحمد', '0599444444');

        $this->assertSame($existing->id, $customer->id);
        $this->assertSame('0599444444', $customer->fresh()->phone);
    }

    public function test_find_or_create_does_not_overwrite_an_existing_phone(): void
    {
        $existing = Customer::factory()->create(['name' => 'خالد عمر', 'phone' => '0599555555']);

        $customer = $this->service()->findOrCreate('خالد عمر', '0599999999');

        $this->assertSame($existing->id, $customer->id);
        $this->assertSame('0599555555', $customer->fresh()->phone);
    }

    public function test_find_or_create_returns_the_pre_existing_row_instead_of_a_duplicate(): void
    {
        $winner = Customer::create(['name' => 'X']);

        $customer = $this->service()->findOrCreate('X', '0599666666');

        $this->assertSame($winner->id, $customer->id);
        $this->assertSame(1, Customer::count());
    }

    public function test_find_or_create_never_creates_more_than_one_row_for_the_same_trimmed_name(): void
    {
        $first = $this->service()->findOrCreate('نور الدين');
        $second = $this->service()->findOrCreate('  نور الدين', '0599777777');
        $third = $this->service()->findOrCreate('نور الدين  ', '0599888888');

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame(1, Customer::count());
        $this->assertSame('0599777777', $first->fresh()->phone);
    }

    public function test_list_returns_paginated_customers_with_order_aggregates(): void
    {
        $customer = Customer::factory()->create(['name' => 'أحمد يوسف']);

        Transfer::factory()->create([
            'customer_id' => $customer->id,
            'amount' => '10.00',
            'created_at' => now()->subDays(2),
        ]);
        Transfer::factory()->create([
            'customer_id' => $customer->id,
            'amount' => '25.50',
            'created_at' => now()->subDay(),
        ]);
        Transfer::factory()->create([
            'customer_id' => $customer->id,
            'amount' => '5.00',
            'created_at' => now(),
        ]);

        $result = $this->service()->list();
        $found = $result->getCollection()->firstWhere('id', $customer->id);

        $this->assertNotNull($found);
        $this->assertSame(3, $found->order_count);
        $this->assertSame('40.50', $found->total_spent);
        $this->assertSame(
            now()->format('Y-m-d H:i'),
            Carbon::parse($found->last_order_at)->format('Y-m-d H:i')
        );
    }

    public function test_list_searches_by_name_or_phone_substring(): void
    {
        $byName = Customer::factory()->create(['name' => 'محمود سالم', 'phone' => '0512345678']);
        $byPhone = Customer::factory()->create(['name' => 'وليد فارس', 'phone' => '0598765432']);
        Customer::factory()->create(['name' => 'غير ذلك', 'phone' => '0500000000']);

        $result = $this->service()->list('محمود');
        $this->assertSame([$byName->id], $result->getCollection()->pluck('id')->all());

        $result = $this->service()->list('8765432');
        $this->assertSame([$byPhone->id], $result->getCollection()->pluck('id')->all());
    }

    public function test_find_eager_loads_transfers_latest_first_with_line_items(): void
    {
        $customer = Customer::factory()->create();

        $older = Transfer::factory()->create(['customer_id' => $customer->id, 'created_at' => now()->subDay()]);
        $newer = Transfer::factory()->create(['customer_id' => $customer->id, 'created_at' => now()]);
        $newer->lineItems()->create(['name' => 'شاي', 'quantity' => 2, 'price' => '3.00']);

        $found = $this->service()->find($customer->id);

        $this->assertSame($newer->id, $found->transfers->first()->id);
        $this->assertSame($older->id, $found->transfers->last()->id);
        $this->assertSame('شاي', $found->transfers->first()->lineItems->first()->name);
    }

    public function test_find_throws_not_found_for_a_bad_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service()->find('does-not-exist');
    }
}
