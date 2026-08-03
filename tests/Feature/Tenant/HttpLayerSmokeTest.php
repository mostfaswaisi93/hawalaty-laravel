<?php

namespace Tests\Feature\Tenant;

use App\Enums\PaymentMethod;
use App\Models\Tenant\Customer;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\TransferService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

class HttpLayerSmokeTest extends TenantTestCase
{
    public function test_a_member_without_the_permission_is_forbidden(): void
    {
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

            ->assertRedirect(route('transfers.created', Transfer::firstOrFail()->id));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/transfers')->assertRedirect('/login');
    }

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

    public function test_a_member_without_the_permission_sees_only_their_own_devices(): void
    {
        $powerless = Role::factory()->create();
        $member = TeamMember::factory()->create(['role_id' => $powerless->id]);

        $this->actingAs($member, 'tenant')
            ->get('/devices')
            ->assertOk()
            ->assertViewHas('manages', false);
    }

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

    public static function paginatedPages(): array
    {
        return [
            'accounts' => ['/accounts', 'accounts'],
            'customers' => ['/customers', 'customers'],
            'expenses' => ['/expenses', 'expenses'],
            'history' => ['/history', 'transfers'],
            'team' => ['/team', 'members'],
            'activity log' => ['/activity-log', 'logs'],

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

        $this->actingAs($owner, 'tenant')->get($uri.'?per_page=99999')
            ->assertOk()
            ->assertViewHas('perPage', 20);
    }

    public function test_the_customer_detail_page_paginates_orders_and_keeps_totals_exact(): void
    {
        Notification::fake();

        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
        $item = MenuItem::factory()->create(['price' => '10.00']);
        $customer = Customer::factory()->create(['name' => 'كثير الطلبات', 'phone' => '0599123123']);

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

        $this->actingAs($owner, 'tenant')->get('/activity-log?per_page=99999')
            ->assertOk()
            ->assertViewHas('perPage', 20);
    }

    public function test_a_rendered_tenant_page_carries_a_working_skip_link(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $html = $this->actingAs($owner, 'tenant')->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('href="#main-content"', $html);
        $this->assertMatchesRegularExpression('/<main[^>]*id="main-content"[^>]*tabindex="-1"/', $html);

        $this->assertLessThan(
            strpos($html, '<aside'),
            strpos($html, 'href="#main-content"'),
            'The skip link renders after the sidebar, so it is not the first tab stop.',
        );
    }

    public function test_the_weekly_chart_labels_each_bar_with_its_own_day(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $response = $this->actingAs($owner, 'tenant')->get('/dashboard')->assertOk();

        $weekly = $response->viewData('weeklyActivity');
        $labels = [__('أحد'), __('اثنين'), __('ثلاثاء'), __('أربعاء'), __('خميس'), __('جمعة'), __('سبت')];
        $html = $response->getContent();

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
