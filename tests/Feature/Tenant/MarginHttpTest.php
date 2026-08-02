<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\MenuService;
use App\Services\Tenant\StockService;
use App\Services\Tenant\TransferService;
use Illuminate\Support\Facades\Notification;
use Tests\TenantTestCase;

class MarginHttpTest extends TenantTestCase
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

    private function soldItem(): MenuItem
    {
        $item = MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create(['name' => 'بقالة'])->id,
            'name' => 'زيت زيتون',
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'LTR',
            'price' => 100,
            'cost' => 60,
        ]);

        app(StockService::class)->receive($item, 10, 60);

        app(TransferService::class)->create([
            'customer' => 'أبو أحمد',
            'paymentMethod' => 'cash',
            'hasChange' => false,
            'items' => [['itemId' => $item->id, 'name' => $item->name, 'quantity' => 2, 'price' => '100']],
        ]);

        return $item;
    }

    public function test_the_margin_page_renders_with_its_three_views(): void
    {
        $item = $this->soldItem();
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')->get('/margins')->assertOk()->assertSee($item->name);
        $this->actingAs($owner, 'tenant')->get('/margins?view=categories')->assertOk()->assertSee('بقالة');
        $this->actingAs($owner, 'tenant')->get('/margins?view=abc')->assertOk()->assertSee($item->name);
    }

    public function test_the_pricing_and_valuation_pages_render(): void
    {
        $this->soldItem();
        $owner = $this->owner();

        MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => 'خسران',
            'kind' => 'goods',
            'track_stock' => true,
            'price' => 10,
            'cost' => 40,
        ]);

        $this->actingAs($owner, 'tenant')->get('/margins/pricing')->assertOk()->assertSee('خسران');
        $this->actingAs($owner, 'tenant')->get('/margins/valuation')->assertOk();
    }

    /**
     * The static segments must win over the wildcard, or /margins/pricing
     * resolves as an item whose id happens to be "pricing".
     */
    public function test_the_named_routes_are_not_swallowed_by_the_wildcard(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')->get('/margins/pricing')->assertOk()->assertSee(__('الهامش المستهدف %'));
        $this->actingAs($owner, 'tenant')->get('/margins/valuation')->assertOk()->assertSee(__('قيمة المخزون'));
    }

    public function test_the_price_history_page_renders(): void
    {
        $category = MenuCategory::factory()->create();
        $owner = $this->owner();

        $item = app(MenuService::class)->createItem([
            'menuCategoryId' => $category->id,
            'name' => 'شامبو',
            'price' => 20,
        ]);

        app(MenuService::class)->updateItem($item->fresh(), ['price' => 26]);

        $this->actingAs($owner, 'tenant')
            ->get("/margins/history/{$item->id}")
            ->assertOk()
            ->assertSee('شامبو')
            ->assertSee('26.00');
    }

    public function test_an_out_of_range_target_margin_is_clamped_rather_than_rejected(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')->get('/margins/pricing?target=9999')->assertOk();
        $this->actingAs($owner, 'tenant')->get('/margins/pricing?target=-40')->assertOk();
    }

    public function test_a_backwards_date_range_is_refused(): void
    {
        $this->actingAs($this->owner(), 'tenant')
            ->get('/margins?from=2026-05-01&to=2026-01-01')
            ->assertSessionHasErrors('to');
    }

    public function test_a_cashier_cannot_read_the_margin_reports(): void
    {
        $roles = $this->seedStandardRoles();
        $cashier = TeamMember::factory()->create(['role_id' => $roles['staff']->id]);

        // Figures nobody can act on are figures nobody should be handed: the
        // catalogue permission is what these screens are written for.
        $this->actingAs($cashier, 'tenant')->get('/margins')->assertRedirect('/dashboard');
        $this->actingAs($cashier, 'tenant')->get('/margins/pricing')->assertRedirect('/dashboard');
        $this->actingAs($cashier, 'tenant')->get('/margins/valuation')->assertRedirect('/dashboard');
    }

    public function test_a_services_workspace_gets_the_page_without_stock_sections(): void
    {
        $this->bindCurrentLicense(BusinessType::Services);

        $this->actingAs($this->owner(), 'tenant')
            ->get('/margins/valuation')
            ->assertOk()
            ->assertDontSee(__('تفصيل التقييم'));
    }
}
