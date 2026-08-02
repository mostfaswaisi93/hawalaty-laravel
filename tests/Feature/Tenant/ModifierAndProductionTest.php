<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Enums\StockMovementType;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Modifier;
use App\Models\Tenant\ModifierGroup;
use App\Models\Tenant\ProductionRun;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\ModifierService;
use App\Services\Tenant\ProductionService;
use App\Services\Tenant\RecipeService;
use App\Services\Tenant\StockService;
use RuntimeException;
use Tests\TenantTestCase;

class ModifierAndProductionTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindCurrentLicense(BusinessType::Restaurant);
    }

    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    private function item(string $name, array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => $name,
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'H87',
            'cost' => 1,
            'price' => 10,
        ], $attributes));
    }

    private function location(): Location
    {
        return Location::default() ?? Location::create([
            'name' => 'Kitchen', 'kind' => 'store', 'is_default' => true, 'active' => true,
        ]);
    }

    private function group(array $attributes = []): ModifierGroup
    {
        return ModifierGroup::create(array_merge([
            'name' => 'الحجم',
            'selection' => 'single',
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 1,
            'active' => true,
        ], $attributes));
    }

    // -- Modifiers ---------------------------------------------------------

    public function test_options_are_priced_from_the_database_not_the_request(): void
    {
        $owner = $this->owner();
        $this->location();

        $burger = $this->item('برغر', ['track_stock' => false, 'price' => 20]);
        $group = $this->group(['name' => 'إضافات', 'selection' => 'multiple', 'max_select' => 3]);
        $cheese = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'جبنة زيادة', 'price_delta' => 5, 'active' => true]);
        $burger->modifierGroups()->attach($group->id);

        $this->actingAs($owner, 'tenant')
            ->post('/transfers', [
                'customer' => 'أبو أحمد',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                // The browser claims the burger is free. It is not believed.
                'items' => [[
                    'itemId' => $burger->id,
                    'quantity' => 2,
                    'name' => $burger->name,
                    'price' => '0',
                    'modifiers' => [$cheese->id],
                ]],
            ])
            ->assertRedirect();

        // (20 + 5) x 2
        $this->assertSame('50.00', Transfer::sole()->amount);
    }

    public function test_the_chosen_options_are_frozen_onto_the_line(): void
    {
        $owner = $this->owner();
        $this->location();

        $burger = $this->item('برغر', ['track_stock' => false, 'price' => 20]);
        $group = $this->group(['name' => 'إضافات', 'selection' => 'multiple']);
        $cheese = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'جبنة زيادة', 'price_delta' => 5, 'active' => true]);
        $burger->modifierGroups()->attach($group->id);

        $this->actingAs($owner, 'tenant')->post('/transfers', [
            'customer' => 'عميل',
            'paymentMethod' => 'cash',
            'hasChange' => '0',
            'items' => [['itemId' => $burger->id, 'quantity' => 1, 'name' => $burger->name, 'price' => '20', 'modifiers' => [$cheese->id]]],
        ]);

        $line = Transfer::sole()->lineItems()->with('modifiers')->sole();

        $this->assertSame('جبنة زيادة', $line->modifiers->sole()->name);
        $this->assertSame('5.000', $line->modifiers->sole()->price_delta);
        $this->assertSame(25.0, $line->unitPrice());
    }

    public function test_an_option_not_offered_on_the_item_is_ignored(): void
    {
        $burger = $this->item('برغر', ['track_stock' => false]);
        $group = $this->group(['name' => 'إضافات', 'selection' => 'multiple']);
        $stray = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'دخيل', 'price_delta' => 99, 'active' => true]);

        // The group was never attached to the burger.
        $resolved = app(ModifierService::class)->resolveFor($burger, [$stray->id]);

        $this->assertTrue($resolved->isEmpty());
    }

    public function test_a_required_group_cannot_be_skipped(): void
    {
        $drink = $this->item('قهوة', ['track_stock' => false]);
        $group = $this->group(['name' => 'الحجم', 'is_required' => true]);
        Modifier::create(['modifier_group_id' => $group->id, 'name' => 'كبير', 'price_delta' => 3, 'active' => true]);
        $drink->modifierGroups()->attach($group->id);

        $this->expectException(RuntimeException::class);

        app(ModifierService::class)->resolveFor($drink->fresh('modifierGroups'), []);
    }

    public function test_a_single_choice_group_refuses_two(): void
    {
        $drink = $this->item('قهوة', ['track_stock' => false]);
        $group = $this->group(['name' => 'الحجم']);
        $small = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'صغير', 'active' => true]);
        $large = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'كبير', 'price_delta' => 3, 'active' => true]);
        $drink->modifierGroups()->attach($group->id);

        $this->expectException(RuntimeException::class);

        app(ModifierService::class)->resolveFor($drink->fresh('modifierGroups'), [$small->id, $large->id]);
    }

    public function test_an_option_that_consumes_stock_depletes_it(): void
    {
        $owner = $this->owner();
        $this->location();

        $cheeseStock = $this->item('جبنة', ['unit_code' => 'KGM']);
        app(StockService::class)->receive($cheeseStock, 10, 20);

        $burger = $this->item('برغر', ['track_stock' => false, 'price' => 20]);
        $group = $this->group(['name' => 'إضافات', 'selection' => 'multiple']);
        $extra = Modifier::create([
            'modifier_group_id' => $group->id,
            'name' => 'جبنة زيادة',
            'price_delta' => 5,
            'component_item_id' => $cheeseStock->id,
            'component_quantity' => 0.03,
            'active' => true,
        ]);
        $burger->modifierGroups()->attach($group->id);

        $this->actingAs($owner, 'tenant')->post('/transfers', [
            'customer' => 'عميل',
            'paymentMethod' => 'cash',
            'hasChange' => '0',
            'items' => [['itemId' => $burger->id, 'quantity' => 2, 'name' => $burger->name, 'price' => '20', 'modifiers' => [$extra->id]]],
        ]);

        // 10 - (0.03 x 2)
        $this->assertSame(9.94, app(StockService::class)->onHand($cheeseStock->id));
    }

    public function test_the_modifiers_screen_is_closed_to_a_vertical_without_them(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);

        $this->actingAs($this->owner(), 'tenant')->get('/modifiers')->assertForbidden();
    }

    public function test_the_modifier_screens_render(): void
    {
        $owner = $this->owner();
        $group = $this->group();
        Modifier::create(['modifier_group_id' => $group->id, 'name' => 'كبير', 'price_delta' => 3, 'active' => true]);

        $this->actingAs($owner, 'tenant')->get('/modifiers')->assertOk()->assertSee('الحجم');
        $this->actingAs($owner, 'tenant')->get('/recipes')->assertOk();
    }

    // -- Production --------------------------------------------------------

    public function test_a_production_run_consumes_components_and_creates_goods(): void
    {
        // A bakery produces to stock and tracks lots.
        $this->bindCurrentLicense(BusinessType::Bakery);

        $this->location();

        $flour = $this->item('طحين', ['unit_code' => 'KGM', 'cost' => 3]);
        $loaf = $this->item('رغيف', ['price' => 2, 'cost' => 0]);

        app(StockService::class)->receive($flour, 100, 3);
        app(RecipeService::class)->replace($loaf, [['componentId' => $flour->id, 'quantity' => 0.2]]);

        $run = app(ProductionService::class)->create([
            'itemId' => $loaf->id,
            'locationId' => Location::default()->id,
            'quantity' => 50,
            'batchNumber' => 'BAKE-1',
            'expiresAt' => now()->addDay()->toDateString(),
        ]);

        app(ProductionService::class)->post($run);

        // 50 loaves x 0.2 kg = 10 kg of flour.
        $this->assertSame(90.0, app(StockService::class)->onHand($flour->id));
        $this->assertSame(50.0, app(StockService::class)->onHand($loaf->id));

        // Cost flows through: 10 kg x 3 = 30, over 50 loaves = 0.6 each.
        $this->assertSame('0.600', $run->fresh()->unit_cost);
        $this->assertSame('0.600', $loaf->fresh()->cost);
    }

    public function test_a_production_run_opens_a_lot_for_its_output(): void
    {
        $this->bindCurrentLicense(BusinessType::Bakery);
        $this->location();

        $flour = $this->item('طحين', ['unit_code' => 'KGM', 'cost' => 3]);
        $loaf = $this->item('رغيف', ['price' => 2]);

        app(StockService::class)->receive($flour, 100, 3);
        app(RecipeService::class)->replace($loaf, [['componentId' => $flour->id, 'quantity' => 0.2]]);

        $run = app(ProductionService::class)->create([
            'itemId' => $loaf->id,
            'locationId' => Location::default()->id,
            'quantity' => 20,
            'batchNumber' => 'BAKE-2',
            'expiresAt' => now()->addDay()->toDateString(),
        ]);

        app(ProductionService::class)->post($run);

        $movement = StockMovement::where('type', StockMovementType::Production)->sole();

        $this->assertNotNull($movement->batch_id);
        $this->assertSame('BAKE-2', $movement->batch_number);
    }

    public function test_an_item_without_a_recipe_cannot_be_produced(): void
    {
        $this->bindCurrentLicense(BusinessType::Bakery);
        $this->location();

        $item = $this->item('صنف');

        $this->expectException(RuntimeException::class);

        app(ProductionService::class)->create([
            'itemId' => $item->id,
            'locationId' => Location::default()->id,
            'quantity' => 1,
        ]);
    }

    public function test_an_assemble_to_order_item_cannot_be_produced(): void
    {
        $this->bindCurrentLicense(BusinessType::Bakery);
        $this->location();

        $flour = $this->item('طحين', ['unit_code' => 'KGM']);
        $dish = $this->item('طبق', ['track_stock' => false]);

        app(RecipeService::class)->replace($dish, [['componentId' => $flour->id, 'quantity' => 1]]);

        $this->expectException(RuntimeException::class);

        app(ProductionService::class)->create([
            'itemId' => $dish->id,
            'locationId' => Location::default()->id,
            'quantity' => 1,
        ]);
    }

    public function test_a_posted_run_cannot_be_reposted(): void
    {
        $this->bindCurrentLicense(BusinessType::Bakery);
        $this->location();

        $flour = $this->item('طحين', ['unit_code' => 'KGM', 'cost' => 3]);
        $loaf = $this->item('رغيف');

        app(StockService::class)->receive($flour, 100, 3);
        app(RecipeService::class)->replace($loaf, [['componentId' => $flour->id, 'quantity' => 0.2]]);

        $run = app(ProductionService::class)->create([
            'itemId' => $loaf->id,
            'locationId' => Location::default()->id,
            'quantity' => 10,
            'batchNumber' => 'BAKE-3',
            'expiresAt' => now()->addDay()->toDateString(),
        ]);

        app(ProductionService::class)->post($run);

        $this->expectException(RuntimeException::class);

        app(ProductionService::class)->post($run->fresh());
    }

    public function test_the_production_screens_render(): void
    {
        $this->bindCurrentLicense(BusinessType::Bakery);
        $this->location();

        $owner = $this->owner();
        $flour = $this->item('طحين', ['unit_code' => 'KGM', 'cost' => 3]);
        $loaf = $this->item('رغيف');

        app(RecipeService::class)->replace($loaf, [['componentId' => $flour->id, 'quantity' => 0.2]]);

        $this->actingAs($owner, 'tenant')->get('/production')->assertOk();

        $run = ProductionRun::create([
            'menu_item_id' => $loaf->id,
            'location_id' => Location::default()->id,
            'quantity' => 5,
            'status' => 'draft',
        ]);

        $this->actingAs($owner, 'tenant')->get("/production/{$run->id}")->assertOk();
    }

    public function test_production_is_closed_to_a_vertical_without_recipes(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);

        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')->get('/production')->assertForbidden();
        $this->actingAs($owner, 'tenant')->get('/recipes')->assertForbidden();
    }

    public function test_the_till_accepts_a_fractional_quantity(): void
    {
        $this->bindCurrentLicense(BusinessType::Produce);
        $this->location();

        $owner = $this->owner();
        $tomatoes = $this->item('بندورة', ['unit_code' => 'KGM', 'price' => 4]);

        app(StockService::class)->receive($tomatoes, 100, 2);

        $this->actingAs($owner, 'tenant')
            ->post('/transfers', [
                'customer' => 'عميل',
                'paymentMethod' => 'cash',
                'hasChange' => '0',
                'items' => [['itemId' => $tomatoes->id, 'quantity' => '1.35', 'name' => $tomatoes->name, 'price' => '4']],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(98.65, app(StockService::class)->onHand($tomatoes->id));
        $this->assertSame('5.40', Transfer::sole()->amount);
    }
}
