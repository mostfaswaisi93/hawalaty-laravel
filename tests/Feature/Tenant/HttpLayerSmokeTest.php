<?php

namespace Tests\Feature\Tenant;

use App\Enums\PaymentMethod;
use App\Models\Tenant\Customer;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\TransferService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
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
            // A created order lands on its own confirmation page, where the
            // operator either prints it or starts the next one.
            ->assertRedirect(route('transfers.created', \App\Models\Tenant\Transfer::firstOrFail()->id));
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
     * These exist because the pagination sweep broke `/accounts` and `/expenses`
     * with a 500 that no test caught - the controllers called `$this->perPage()`
     * before the trait was applied. A compile check cannot see that; only
     * issuing the request can.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function tablePages(): array
    {
        return [
            'accounts' => ['/accounts', 'accounts'],
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
            'accounts' => ['/accounts', 'accounts'],
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
        $customer = Customer::factory()->create(['name' => 'كثير الطلبات', 'phone' => '0599123123']);

        // 25 orders: more than one page of 20. The PHONE is what links them to
        // the customer - the name alone no longer identifies anyone.
        foreach (range(1, 25) as $ignored) {
            app(TransferService::class)->create([
                'customer' => $customer->name,
                'customerPhone' => $customer->phone,
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

    /**
     * The skip link, checked in RENDERED output rather than in the template.
     * ViewMarkupTest asserts the shell declares it; this proves it survives to
     * the browser, with its target present and focusable on the same page.
     */
    public function test_a_rendered_tenant_page_carries_a_working_skip_link(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $html = $this->actingAs($owner, 'tenant')->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('href="#main-content"', $html);
        $this->assertMatchesRegularExpression('/<main[^>]*id="main-content"[^>]*tabindex="-1"/', $html);

        // It has to come BEFORE the navigation, or it skips nothing.
        $this->assertLessThan(
            strpos($html, '<aside'),
            strpos($html, 'href="#main-content"'),
            'The skip link renders after the sidebar, so it is not the first tab stop.',
        );
    }

    /**
     * The weekly chart's day captions must run in the same direction as its
     * bars.
     *
     * They were two sibling rows, and only the bars row carried `dir="ltr"` -
     * so the bars ran oldest-left while the captions below them still ran
     * oldest-RIGHT. The two were mirrored, and every bar was labelled with the
     * wrong day: one order placed on a Monday appeared under Thursday. Nothing
     * errored, the page looked plausible, and the number was simply wrong.
     *
     * What CANNOT catch it is reading the order the day names appear in the
     * HTML: both layouts emit them oldest-first, because the loop does. The
     * flip happens in CSS at render time, and source order never sees it. The
     * first version of this test asserted exactly that and passed happily
     * against the broken markup.
     *
     * The property that actually holds only in the fixed layout is STRUCTURAL:
     * each caption sits inside the column it belongs to, so `dir` can only ever
     * apply to both together. In the broken layout every caption came after
     * every bar, in a row of its own.
     */
    public function test_the_weekly_chart_labels_each_bar_with_its_own_day(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $response = $this->actingAs($owner, 'tenant')->get('/dashboard')->assertOk();

        $weekly = $response->viewData('weeklyActivity');
        $labels = [__('أحد'), __('اثنين'), __('ثلاثاء'), __('أربعاء'), __('خميس'), __('جمعة'), __('سبت')];
        $html = $response->getContent();

        // Where the chart starts, so day names used elsewhere on the page (the
        // heatmap has its own weekday column) cannot be mistaken for these.
        $chartAt = strpos($html, (string) __('إجمالي طلبات هذا الأسبوع'));
        $this->assertNotFalse($chartAt, 'The weekly chart is gone.');

        $barAt = [];
        $labelAt = [];
        $cursor = $chartAt;

        foreach ($weekly as $i => $day) {
            $name = $labels[Carbon::parse($day['date'])->dayOfWeek];

            $bar = strpos($html, 'grow-bar', $cursor);
            $label = strpos($html, '>'.$name.'</span>', $cursor);

            $this->assertNotFalse($bar, "Bar {$i} is missing from the weekly chart.");
            $this->assertNotFalse($label, "Day \"{$name}\" is missing from the weekly chart.");

            $barAt[$i] = $bar;
            $labelAt[$i] = $label;
            $cursor = max($bar, $label) + 1;
        }

        // Each caption must precede the NEXT column's bar. Pulled out into a
        // row of their own, every caption would fall after every bar instead.
        foreach (array_slice(array_keys($weekly), 0, -1) as $i) {
            $this->assertLessThan(
                $barAt[$i + 1],
                $labelAt[$i],
                "The day caption for column {$i} renders after the next column's bar - ".
                'captions are in a separate row and will mirror against the bars under RTL.',
            );
        }
    }
}
