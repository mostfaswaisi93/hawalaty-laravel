<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\CustomerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Tests\TenantTestCase;

/**
 * Regression coverage for CustomerService.
 *
 * The identity rule is the important part: a customer IS a phone number. Name
 * matching was wrong both ways - it merged two different people who shared a
 * name, and it split one person whose name was typed two ways. Several tests
 * below pin both directions.
 */
class CustomerServiceTest extends TenantTestCase
{
    private function service(): CustomerService
    {
        return app(CustomerService::class);
    }

    public function test_find_or_create_trims_before_matching_and_creating(): void
    {
        $customer = $this->service()->findOrCreate('  محمد  ', '  0599111111  ');

        $this->assertSame('محمد', $customer->name);
        $this->assertSame('0599111111', $customer->phone);
        $this->assertSame(1, Customer::count());
    }

    /**
     * IDENTITY IS THE PHONE. Two different people can share a name; they cannot
     * share a number.
     */
    public function test_the_same_phone_is_the_same_customer_even_with_a_different_name(): void
    {
        $existing = Customer::factory()->create(['name' => 'محمد خالد', 'phone' => '0599222222']);

        $customer = $this->service()->findOrCreate('محمد خالد الأحمد', '0599222222');

        $this->assertSame($existing->id, $customer->id);
        $this->assertSame(1, Customer::count());
    }

    /** The converse: a shared name must NOT merge two different numbers. */
    public function test_the_same_name_on_a_different_phone_is_a_different_customer(): void
    {
        $first = $this->service()->findOrCreate('أحمد', '0599000001');
        $second = $this->service()->findOrCreate('أحمد', '0599000002');

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Customer::count());
    }

    /**
     * The name is a LABEL on the number, so the latest spelling wins - fixing a
     * typo on a new order corrects the record instead of forking it.
     */
    public function test_a_corrected_name_updates_the_record_rather_than_duplicating_it(): void
    {
        $this->service()->findOrCreate('احمد محمد', '0599333333');
        $customer = $this->service()->findOrCreate('أحمد محمد', '0599333333');

        $this->assertSame('أحمد محمد', $customer->fresh()->name);
        $this->assertSame(1, Customer::count());
    }

    public function test_an_empty_name_never_blanks_out_a_known_one(): void
    {
        $this->service()->findOrCreate('سارة علي', '0599444444');
        $customer = $this->service()->findOrCreate('   ', '0599444444');

        $this->assertSame('سارة علي', $customer->fresh()->name);
    }

    /**
     * With no phone there is nothing to identify, so no half-record is created.
     * The order still carries the typed name in `transfers.customer`.
     */
    public function test_no_customer_is_created_without_a_phone(): void
    {
        $this->assertNull($this->service()->findOrCreate('زبون عابر'));
        $this->assertNull($this->service()->findOrCreate('زبون عابر', ''));
        $this->assertNull($this->service()->findOrCreate('زبون عابر', '   '));

        $this->assertSame(0, Customer::count());
    }

    public function test_repeated_orders_from_one_number_never_duplicate(): void
    {
        $first = $this->service()->findOrCreate('نور الدين', '0599777777');
        $second = $this->service()->findOrCreate('  نور الدين', '0599777777');
        $third = $this->service()->findOrCreate('نور الدين  ', '  0599777777');

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame(1, Customer::count());
    }

    /**
     * The lookup-then-insert is not atomic, so the UNIQUE index on `phone` is
     * what turns a concurrent double-insert into a catchable error rather than a
     * silent duplicate that splits the customer's history.
     */
    public function test_a_duplicate_phone_insert_is_rejected_by_the_database(): void
    {
        Customer::create(['name' => 'أول', 'phone' => '0599888888']);

        $this->expectException(QueryException::class);

        Customer::create(['name' => 'ثانٍ', 'phone' => '0599888888']);
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

    /**
     * find() must NOT eager-load transfers - it returns SQL aggregates, and the
     * orders come back paginated from transfersFor().
     */
    public function test_find_returns_aggregates_without_loading_every_transfer(): void
    {
        $customer = Customer::factory()->create();
        Transfer::factory()->create(['customer_id' => $customer->id, 'amount' => '10.00']);
        Transfer::factory()->create(['customer_id' => $customer->id, 'amount' => '15.50']);

        $found = $this->service()->find($customer->id);

        $this->assertSame(2, $found->order_count);
        $this->assertSame('25.50', $found->total_spent);
        $this->assertFalse(
            $found->relationLoaded('transfers'),
            'find() eager-loaded transfers again - the detail page will load every order.'
        );
    }

    public function test_transfers_for_paginates_latest_first_with_line_items(): void
    {
        $customer = Customer::factory()->create();

        $older = Transfer::factory()->create(['customer_id' => $customer->id, 'created_at' => now()->subDay()]);
        $newer = Transfer::factory()->create(['customer_id' => $customer->id, 'created_at' => now()]);
        $newer->lineItems()->create(['name' => 'شاي', 'quantity' => 2, 'price' => '3.00']);

        $page = $this->service()->transfersFor($customer);

        $this->assertSame($newer->id, $page->getCollection()->first()->id);
        $this->assertSame($older->id, $page->getCollection()->last()->id);
        $this->assertSame('شاي', $page->getCollection()->first()->lineItems->first()->name);
    }

    public function test_transfers_for_respects_the_page_size(): void
    {
        $customer = Customer::factory()->create();
        Transfer::factory()->count(5)->create(['customer_id' => $customer->id]);

        $page = $this->service()->transfersFor($customer, 2);

        $this->assertSame(5, $page->total());
        $this->assertCount(2, $page->getCollection());
        $this->assertSame(3, $page->lastPage());
    }

    public function test_find_throws_not_found_for_a_bad_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service()->find('does-not-exist');
    }
}
