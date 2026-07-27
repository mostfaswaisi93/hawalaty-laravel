<?php

namespace Tests\Feature\Tenant;

use App\Enums\PaymentMethod;
use App\Models\Tenant\Customer;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\TransferService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * Proof-of-concept: confirms actingAs($member, 'tenant') plus the
 * manually-configured `tenant` connection from TenantTestCase is enough to
 * exercise tenant web routes end-to-end, without needing
 * session('license_code') - ResolveTenantConnectionFromSession only
 * re-resolves the connection when that session key is present (see the
 * middleware's docblock), so it simply passes through here and leaves
 * whatever TenantTestCase already configured in place.
 */
class HttpLayerSmokeTest extends TenantTestCase
{
    public function test_a_member_without_the_permission_is_forbidden(): void
    {
        // None of the 3 standard roles lack transfers.create (even Staff has
        // only that one) - a bare custom role with zero permissions attached
        // is the only way to exercise the "no permission" path.
        $powerlessRole = Role::factory()->create();
        $member = TeamMember::factory()->create(['role_id' => $powerlessRole->id]);
        $item = MenuItem::factory()->create();

        $this->actingAs($member, 'tenant')
            ->post('/transfers', [
                'customer' => 'Test',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                'items' => [['itemId' => $item->id, 'quantity' => 1, 'name' => $item->name, 'price' => (string) $item->price]],
            ])
            ->assertForbidden();
    }

    public function test_a_member_with_the_permission_can_create_a_transfer(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
        $item = MenuItem::factory()->create();

        $this->actingAs($owner, 'tenant')
            ->post('/transfers', [
                'customer' => 'Test',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                'items' => [['itemId' => $item->id, 'quantity' => 1, 'name' => $item->name, 'price' => (string) $item->price]],
            ])
            ->assertRedirect(route('transfers.index'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/transfers')->assertRedirect('/login');
    }

    /**
     * The devices page now renders a license-wide allowance meter fed by
     * DeviceRegistrar. `currentLicense` is not bound in this harness, so this
     * also pins the "no license bound => unlimited, do not crash" path - the
     * alternative (treating it as a zero allowance) would lock everyone out.
     */
    public function test_the_devices_page_renders_with_the_allowance_meter(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $this->actingAs($owner, 'tenant')
            ->get('/devices')
            ->assertOk()
            ->assertViewHas('usage', fn ($usage) => $usage['used'] === 0 && $usage['unlimited'] === true)
            ->assertViewHas('manages', true);
    }

    /** A member without DevicesManage sees only their own devices. */
    public function test_a_member_without_the_permission_sees_only_their_own_devices(): void
    {
        $powerless = Role::factory()->create();
        $member = TeamMember::factory()->create(['role_id' => $powerless->id]);

        $this->actingAs($member, 'tenant')
            ->get('/devices')
            ->assertOk()
            ->assertViewHas('manages', false);
    }

    /**
     * Every tenant page carrying a table, hit for real.
     *
     * These exist because the pagination sweep broke `/banks` and `/expenses`
     * with a 500 that no test caught - the controllers called `$this->perPage()`
     * before the trait was applied. A compile check cannot see that; only
     * issuing the request can.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function tablePages(): array
    {
        return [
            'banks' => ['/banks', 'banks'],
            'customers' => ['/customers', 'customers'],
            'expenses' => ['/expenses', 'expenses'],
            'history' => ['/history', 'transfers'],
            'team' => ['/team', 'members'],
            'activity log' => ['/activity-log', 'logs'],
            'menu' => ['/menu', 'categories'],
            'reports' => ['/reports', 'transfers'],
        ];
    }

    #[DataProvider('tablePages')]
    public function test_a_tenant_table_page_renders(string $uri, string $viewKey): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $this->actingAs($owner, 'tenant')->get($uri)
            ->assertOk()
            ->assertViewHas($viewKey);
    }

    /**
     * The paginated ones must actually expose a paginator plus the page size
     * the footer binds to - a plain Collection would render the footer blank.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function paginatedPages(): array
    {
        return [
            'banks' => ['/banks', 'banks'],
            'customers' => ['/customers', 'customers'],
            'expenses' => ['/expenses', 'expenses'],
            'history' => ['/history', 'transfers'],
            'team' => ['/team', 'members'],
            'activity log' => ['/activity-log', 'logs'],
            // The report's transfer list was fetched with a bare ->get(): a
            // busy tenant's month rendered every row in one table.
            'reports' => ['/reports', 'transfers'],
        ];
    }

    #[DataProvider('paginatedPages')]
    public function test_a_table_page_exposes_a_paginator_and_page_size(string $uri, string $viewKey): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $this->actingAs($owner, 'tenant')->get($uri)
            ->assertOk()
            ->assertViewHas('perPage', 20)
            ->assertViewHas($viewKey, fn ($rows) => $rows instanceof LengthAwarePaginator);

        // A value outside the whitelist must not become an unbounded page size.
        $this->actingAs($owner, 'tenant')->get($uri.'?per_page=99999')
            ->assertOk()
            ->assertViewHas('perPage', 20);
    }

    /**
     * The customer detail page used to eager-load EVERY transfer (and every
     * line item) purely to sum two numbers and render a table. The figures are
     * now SQL aggregates and the list is paginated, so both must still be right
     * while only one page of rows is loaded.
     */
    public function test_the_customer_detail_page_paginates_orders_and_keeps_totals_exact(): void
    {
        Notification::fake();

        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
        $item = MenuItem::factory()->create(['price' => '10.00']);
        $customer = Customer::factory()->create(['name' => 'كثير الطلبات']);

        // 25 orders: more than one page of 20.
        foreach (range(1, 25) as $ignored) {
            app(TransferService::class)->create([
                'customer' => $customer->name,
                'paymentMethod' => PaymentMethod::Cash,
                'items' => [['itemId' => $item->id, 'quantity' => 1]],
            ]);
        }

        $this->actingAs($owner, 'tenant')->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertViewHas('perPage', 20)
            ->assertViewHas('transfers', fn ($rows) => $rows instanceof LengthAwarePaginator
                && $rows->total() === 25
                && $rows->count() === 20)
            // Aggregates cover ALL 25 orders, not just the loaded page.
            ->assertViewHas('customer', fn ($c) => (int) $c->order_count === 25
                && (float) $c->total_spent === 250.0);
    }

    public function test_the_activity_log_paginates_with_a_whitelisted_page_size(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $this->actingAs($owner, 'tenant')->get('/activity-log')
            ->assertOk()
            ->assertViewHas('perPage', 20);

        $this->actingAs($owner, 'tenant')->get('/activity-log?per_page=50')
            ->assertOk()
            ->assertViewHas('perPage', 50);

        // Outside the whitelist falls back to the default rather than
        // honouring an unbounded page size.
        $this->actingAs($owner, 'tenant')->get('/activity-log?per_page=99999')
            ->assertOk()
            ->assertViewHas('perPage', 20);
    }
}
