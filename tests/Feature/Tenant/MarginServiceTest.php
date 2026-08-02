<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Enums\StockMovementType;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\PriceChange;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\MarginService;
use App\Services\Tenant\MenuService;
use App\Services\Tenant\RecipeService;
use App\Services\Tenant\StockService;
use App\Services\Tenant\TransferService;
use Illuminate\Support\Facades\Notification;
use Tests\TenantTestCase;

class MarginServiceTest extends TenantTestCase
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

    private function margins(): MarginService
    {
        return app(MarginService::class);
    }

    private function item(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge([
            'menu_category_id' => MenuCategory::factory()->create(['name' => 'بقالة'])->id,
            'name' => 'زيت زيتون',
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'LTR',
            'price' => 100,
            'cost' => 60,
            'tax_rate' => 0,
            'tax_included' => true,
        ], $attributes));
    }

    /**
     * @param  array<int, array{item: MenuItem, quantity: float}>  $lines
     */
    private function sell(array $lines, float $discount = 0): Transfer
    {
        return app(TransferService::class)->create([
            'customer' => 'أبو أحمد',
            'paymentMethod' => 'cash',
            'hasChange' => false,
            'discount' => $discount,
            'items' => array_map(fn (array $line) => [
                'itemId' => $line['item']->id,
                'name' => $line['item']->name,
                'quantity' => $line['quantity'],
                'price' => (string) $line['item']->price,
            ], $lines),
        ]);
    }

    private function today(): array
    {
        return [now()->toDateString(), now()->toDateString()];
    }

    // -- The core arithmetic -----------------------------------------------

    public function test_margin_is_revenue_less_the_cost_frozen_on_the_outflow(): void
    {
        $item = $this->item();
        app(StockService::class)->receive($item, 10, 60);

        $this->sell([['item' => $item, 'quantity' => 2]]);

        [$from, $to] = $this->today();
        $row = $this->margins()->perItem($from, $to)->firstWhere('item.id', $item->id);

        $this->assertSame(200.0, $row['revenue']);
        $this->assertSame(120.0, $row['cost']);
        $this->assertSame(80.0, $row['margin']);
        $this->assertSame(40.0, $row['margin_percent']);
        $this->assertSame('ledger', $row['basis']);
    }

    /**
     * The whole reason cost is read off the movement rather than off the item.
     */
    public function test_a_later_cost_change_does_not_rewrite_an_earlier_margin(): void
    {
        $item = $this->item();
        app(StockService::class)->receive($item, 10, 60);

        $this->sell([['item' => $item, 'quantity' => 2]]);

        // A pricier delivery arrives after the sale and rolls the average up.
        app(StockService::class)->receive($item, 10, 90);

        [$from, $to] = $this->today();
        $row = $this->margins()->perItem($from, $to)->firstWhere('item.id', $item->id);

        $this->assertSame(120.0, $row['cost']);
        $this->assertSame(80.0, $row['margin']);
    }

    public function test_revenue_excludes_the_tax_the_shop_never_earned(): void
    {
        $item = $this->item(['price' => 115, 'tax_rate' => 15, 'tax_included' => true]);
        app(StockService::class)->receive($item, 10, 60);

        $this->sell([['item' => $item, 'quantity' => 2]]);

        [$from, $to] = $this->today();
        $row = $this->margins()->perItem($from, $to)->firstWhere('item.id', $item->id);

        // 230 taken, of which 30 is tax.
        $this->assertSame(200.0, $row['revenue']);
        $this->assertSame(230.0, $row['gross_revenue']);
        $this->assertSame(80.0, $row['margin']);
    }

    public function test_an_order_discount_is_apportioned_across_its_lines(): void
    {
        $a = $this->item(['name' => 'أ', 'price' => 100, 'cost' => 40]);
        $b = $this->item(['name' => 'ب', 'price' => 300, 'cost' => 100]);

        app(StockService::class)->receive($a, 10, 40);
        app(StockService::class)->receive($b, 10, 100);

        // 400 charged, 40 off - a tenth comes off each line.
        $this->sell([['item' => $a, 'quantity' => 1], ['item' => $b, 'quantity' => 1]], discount: 40);

        [$from, $to] = $this->today();
        $rows = $this->margins()->perItem($from, $to)->keyBy(fn ($row) => $row['item']->id);

        $this->assertSame(90.0, $rows[$a->id]['revenue']);
        $this->assertSame(270.0, $rows[$b->id]['revenue']);
        $this->assertSame(360.0, $this->margins()->summary($from, $to)['revenue']);
    }

    public function test_waste_is_reported_beside_the_margin_not_inside_it(): void
    {
        $item = $this->item();
        app(StockService::class)->receive($item, 10, 60);

        $this->sell([['item' => $item, 'quantity' => 2]]);
        app(StockService::class)->waste($item, 1, 'كسر');

        [$from, $to] = $this->today();
        $summary = $this->margins()->summary($from, $to);

        // A production problem and a pricing problem are different problems.
        $this->assertSame(80.0, $summary['margin']);
        $this->assertSame(60.0, $summary['waste']);
        $this->assertSame(20.0, $summary['margin_after_waste']);
    }

    public function test_categories_roll_up_from_the_same_rows(): void
    {
        $category = MenuCategory::factory()->create(['name' => 'زيوت']);

        $a = $this->item(['menu_category_id' => $category->id, 'name' => 'أ', 'price' => 100, 'cost' => 60]);
        $b = $this->item(['menu_category_id' => $category->id, 'name' => 'ب', 'price' => 50, 'cost' => 20]);

        app(StockService::class)->receive($a, 10, 60);
        app(StockService::class)->receive($b, 10, 20);

        $this->sell([['item' => $a, 'quantity' => 1], ['item' => $b, 'quantity' => 1]]);

        [$from, $to] = $this->today();
        $row = $this->margins()->perCategory($from, $to)->firstWhere('category', 'زيوت');

        $this->assertSame(2, $row['items']);
        $this->assertSame(150.0, $row['revenue']);
        $this->assertSame(70.0, $row['margin']);
    }

    // -- ABC ---------------------------------------------------------------

    public function test_abc_ranks_on_margin_contribution_not_turnover(): void
    {
        // Busy and worthless: large revenue, no margin at all.
        $busy = $this->item(['name' => 'مزدحم', 'price' => 100, 'cost' => 100]);
        $quiet = $this->item(['name' => 'هادئ', 'price' => 50, 'cost' => 10]);

        app(StockService::class)->receive($busy, 100, 100);
        app(StockService::class)->receive($quiet, 100, 10);

        $this->sell([['item' => $busy, 'quantity' => 20], ['item' => $quiet, 'quantity' => 2]]);

        [$from, $to] = $this->today();
        $abc = $this->margins()->abc($from, $to);

        $this->assertSame($quiet->id, $abc->first()['item']->id);
        $this->assertSame('A', $abc->first()['class']);

        // Zero margin contributes nothing and is not classified at all.
        $this->assertNull($abc->firstWhere('item.id', $busy->id));
    }

    // -- Valuation ---------------------------------------------------------

    public function test_valuation_flags_stock_carried_above_what_it_will_return(): void
    {
        $healthy = $this->item(['name' => 'سليم', 'price' => 100, 'cost' => 60]);
        $impaired = $this->item(['name' => 'منخفض', 'price' => 50, 'cost' => 80]);

        app(StockService::class)->receive($healthy, 10, 60);
        app(StockService::class)->receive($impaired, 10, 80);

        $valuation = $this->margins()->valuation();

        $this->assertSame(1400.0, $valuation['total']);

        // IAS 2: the lower of cost and net realisable value. 10 x (80 - 50).
        $this->assertSame(300.0, $valuation['writeDown']);
        $this->assertTrue($valuation['rows']->firstWhere('item.id', $impaired->id)['impaired']);
        $this->assertFalse($valuation['rows']->firstWhere('item.id', $healthy->id)['impaired']);
    }

    public function test_slow_movers_report_capital_not_a_headcount(): void
    {
        $moving = $this->item(['name' => 'يتحرك', 'cost' => 60]);
        $dead = $this->item(['name' => 'راكد', 'cost' => 25]);

        app(StockService::class)->receive($moving, 10, 60);
        app(StockService::class)->receive($dead, 8, 25);

        $this->sell([['item' => $moving, 'quantity' => 1]]);

        $slow = $this->margins()->slowMovers(90);

        $this->assertCount(1, $slow);
        $this->assertSame($dead->id, $slow->first()['item']->id);
        $this->assertSame(200.0, $slow->first()['tied_up']);
        $this->assertNull($slow->first()['last_sold']);
    }

    // -- Pricing -----------------------------------------------------------

    public function test_an_item_sold_below_cost_is_surfaced_with_a_price_that_fixes_it(): void
    {
        $item = $this->item(['price' => 50, 'cost' => 80]);

        $row = $this->margins()->pricing(30.0)->firstWhere('item.id', $item->id);

        $this->assertTrue($row['below_cost']);

        // 80 / (1 - 0.30) = 114.29.
        $this->assertSame(114.29, $row['suggested_price']);
    }

    public function test_a_healthy_item_is_left_off_the_pricing_list(): void
    {
        $this->item(['name' => 'مربح', 'price' => 100, 'cost' => 40]);

        $this->assertTrue($this->margins()->pricing(30.0)->isEmpty());
    }

    public function test_the_suggested_price_carries_tax_the_way_the_item_is_priced(): void
    {
        $item = $this->item(['price' => 50, 'cost' => 80, 'tax_rate' => 15, 'tax_included' => true]);

        $row = $this->margins()->pricing(30.0)->firstWhere('item.id', $item->id);

        // Net 114.29, then the 15% the shelf price is expected to include.
        $this->assertSame(131.43, $row['suggested_price']);
    }

    // -- Price history -----------------------------------------------------

    public function test_a_price_change_records_the_cost_it_was_chosen_against(): void
    {
        $category = MenuCategory::factory()->create();

        $item = app(MenuService::class)->createItem([
            'menuCategoryId' => $category->id,
            'name' => 'شامبو',
            'price' => 20,
        ]);

        $item->forceFill(['cost' => 12])->save();

        app(MenuService::class)->updateItem($item->fresh(), ['price' => 25]);

        $changes = PriceChange::where('menu_item_id', $item->id)->orderBy('created_at')->get();

        $this->assertCount(2, $changes);
        $this->assertNull($changes[0]->old_price);
        $this->assertSame('20.000', $changes[1]->old_price);
        $this->assertSame('25.000', $changes[1]->new_price);
        $this->assertSame('12.000', $changes[1]->cost_at_change);
    }

    public function test_saving_an_item_without_touching_the_price_records_nothing(): void
    {
        $category = MenuCategory::factory()->create();

        $item = app(MenuService::class)->createItem([
            'menuCategoryId' => $category->id,
            'name' => 'شامبو',
            'price' => 20,
        ]);

        app(MenuService::class)->updateItem($item->fresh(), ['name' => 'شامبو كبير', 'price' => 20]);

        $this->assertSame(1, PriceChange::where('menu_item_id', $item->id)->count());
    }

    /**
     * The report the history exists for: a price that was fine when it was set
     * and has been quietly overtaken by its own cost.
     */
    public function test_margin_erosion_is_measured_against_the_cost_at_the_time(): void
    {
        $category = MenuCategory::factory()->create();

        $item = app(MenuService::class)->createItem([
            'menuCategoryId' => $category->id,
            'name' => 'جبنة',
            'price' => 100,
        ]);

        // Priced at 100 against a cost of 50 - a 50% margin at the time.
        PriceChange::where('menu_item_id', $item->id)->update(['cost_at_change' => 50]);

        // Two years of supplier increases later, nobody having touched the price.
        $item->forceFill(['cost' => 85])->save();

        $row = $this->margins()->pricing(30.0)->firstWhere('item.id', $item->id);

        $this->assertSame(15.0, $row['margin_percent']);
        $this->assertSame(50.0, $row['margin_when_priced']);
        $this->assertSame(35.0, $row['eroded_by']);
    }

    // -- Recipes -----------------------------------------------------------

    public function test_an_assembled_dish_is_costed_from_its_recipe_and_says_so(): void
    {
        $this->bindCurrentLicense(BusinessType::Restaurant);

        $bread = $this->item(['name' => 'خبز', 'price' => 0, 'cost' => 2]);
        $cheese = $this->item(['name' => 'جبنة', 'price' => 0, 'cost' => 8]);

        $sandwich = $this->item(['name' => 'ساندويش', 'price' => 25, 'cost' => 0, 'track_stock' => false]);

        app(RecipeService::class)->replace($sandwich, [
            ['componentId' => $bread->id, 'quantity' => 1],
            ['componentId' => $cheese->id, 'quantity' => 0.5],
        ]);

        app(StockService::class)->receive($bread, 100, 2);
        app(StockService::class)->receive($cheese, 100, 8);

        $this->sell([['item' => $sandwich, 'quantity' => 4]]);

        [$from, $to] = $this->today();
        $row = $this->margins()->perItem($from, $to)->firstWhere('item.id', $sandwich->id);

        // 4 x (1 x 2 + 0.5 x 8) = 24.
        $this->assertSame('recipe', $row['basis']);
        $this->assertSame(24.0, $row['cost']);
        $this->assertSame(100.0, $row['revenue']);
    }

    /**
     * The number a kitchen can act on the same day, and the only one that
     * survives the fact that a dish's per-unit cost is always theoretical.
     */
    public function test_over_portioning_shows_up_as_a_consumption_variance(): void
    {
        $this->bindCurrentLicense(BusinessType::Restaurant);

        $cheese = $this->item(['name' => 'جبنة', 'price' => 0, 'cost' => 10]);
        $sandwich = $this->item(['name' => 'ساندويش', 'price' => 25, 'cost' => 0, 'track_stock' => false]);

        app(RecipeService::class)->replace($sandwich, [['componentId' => $cheese->id, 'quantity' => 1]]);

        app(StockService::class)->receive($cheese, 100, 10);
        $this->sell([['item' => $sandwich, 'quantity' => 10]]);

        // Two kilos left the fridge that no order accounts for.
        app(StockService::class)->issue($cheese, 2, StockMovementType::Consumption);

        [$from, $to] = $this->today();
        $row = $this->margins()->consumptionVariance($from, $to)->firstWhere('item.id', $cheese->id);

        $this->assertSame(10.0, $row['theoretical']);
        $this->assertSame(12.0, $row['actual']);
        $this->assertSame(2.0, $row['variance']);
        $this->assertSame(20.0, $row['variance_percent']);
        $this->assertSame(20.0, $row['cost']);
    }

    // -- Verticals without stock -------------------------------------------

    public function test_a_services_workspace_reports_revenue_and_no_invented_cost(): void
    {
        $this->bindCurrentLicense(BusinessType::Services);

        $haircut = MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => 'قصّة',
            'kind' => 'service',
            'track_stock' => false,
            'price' => 40,
            'cost' => 0,
        ]);

        $this->sell([['item' => $haircut, 'quantity' => 3]]);

        [$from, $to] = $this->today();
        $row = $this->margins()->perItem($from, $to)->firstWhere('item.id', $haircut->id);

        $this->assertSame(120.0, $row['revenue']);
        $this->assertSame(0.0, $row['cost']);
        $this->assertSame('none', $row['basis']);

        // And nothing pretends there is stock to value.
        $this->assertSame(0.0, $this->margins()->valuation()['total']);
        $this->assertTrue($this->margins()->slowMovers()->isEmpty());
    }

    public function test_a_period_with_no_sales_reports_zero_rather_than_failing(): void
    {
        $summary = $this->margins()->summary('2020-01-01', '2020-01-31');

        $this->assertSame(0.0, $summary['revenue']);
        $this->assertSame(0.0, $summary['margin']);
        $this->assertNull($summary['margin_percent']);
        $this->assertSame(0, $summary['items']);
    }

    public function test_a_deleted_sale_stops_counting(): void
    {
        $item = $this->item();
        app(StockService::class)->receive($item, 10, 60);

        $transfer = $this->sell([['item' => $item, 'quantity' => 2]]);
        $transfer->delete();

        [$from, $to] = $this->today();

        $this->assertSame(0.0, $this->margins()->summary($from, $to)['revenue']);
    }
}
