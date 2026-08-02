<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Enums\StockMovementType;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\StockMovement;
use App\Services\Tenant\RecipeService;
use App\Services\Tenant\StockService;
use RuntimeException;
use Tests\TenantTestCase;

class RecipeServiceTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Recipes belong to the food verticals; grocery does not have them.
        $this->bindCurrentLicense(BusinessType::Restaurant);
    }

    private function recipes(): RecipeService
    {
        return app(RecipeService::class);
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    /**
     * A raw ingredient: stocked, no recipe.
     */
    private function ingredient(string $name, float $cost = 1, string $unit = 'KGM'): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => $name,
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => $unit,
            'cost' => $cost,
        ]);
    }

    /**
     * A finished item assembled at the till: not stocked, has a recipe.
     */
    private function dish(string $name, float $price = 20, bool $stocked = false): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => $name,
            'kind' => 'goods',
            'track_stock' => $stocked,
            'unit_code' => 'H87',
            'price' => $price,
        ]);
    }

    private function location(): Location
    {
        return Location::default() ?? Location::create([
            'name' => 'Kitchen', 'kind' => 'store', 'is_default' => true, 'active' => true,
        ]);
    }

    public function test_theoretical_cost_sums_the_components(): void
    {
        $bread = $this->ingredient('خبز', 2);
        $meat = $this->ingredient('لحمة', 40);
        $burger = $this->dish('برغر');

        $this->recipes()->replace($burger, [
            ['componentId' => $bread->id, 'quantity' => 1],
            ['componentId' => $meat->id, 'quantity' => 0.15],
        ]);

        // 1 x 2 + 0.15 x 40 = 8
        $this->assertSame(8.0, $this->recipes()->theoreticalCost($burger->fresh()));
    }

    public function test_yield_loss_raises_both_the_draw_and_the_cost(): void
    {
        $onion = $this->ingredient('بصل', 10);
        $dish = $this->dish('سلطة');

        // 0.1 kg ends up in the dish, but a fifth is lost to peeling, so 0.125
        // leaves the shelf.
        $this->recipes()->replace($dish, [
            ['componentId' => $onion->id, 'quantity' => 0.1, 'wastePercent' => 20],
        ]);

        $exploded = $this->recipes()->explode($dish->fresh())->sole();

        $this->assertSame(0.125, $exploded['quantity']);
        $this->assertSame(1.25, $this->recipes()->theoreticalCost($dish->fresh()));
    }

    public function test_an_ingredient_used_twice_is_merged_into_one_draw(): void
    {
        $cheese = $this->ingredient('جبنة', 20);
        $sauce = $this->dish('صلصة');
        $pizza = $this->dish('بيتزا');

        $this->recipes()->replace($sauce, [['componentId' => $cheese->id, 'quantity' => 0.05]]);
        $this->recipes()->replace($pizza, [
            ['componentId' => $sauce->id, 'quantity' => 1],
            ['componentId' => $cheese->id, 'quantity' => 0.1],
        ]);

        $exploded = $this->recipes()->explode($pizza->fresh());

        // Cheese in the sauce and cheese on top is one draw on the shelf.
        $this->assertCount(1, $exploded);
        $this->assertSame(0.15, $exploded->sole()['quantity']);
    }

    public function test_explosion_stops_at_anything_stocked(): void
    {
        $flour = $this->ingredient('طحين', 3);
        $dough = $this->dish('عجينة', 5, stocked: true);
        $bread = $this->dish('رغيف');

        $this->recipes()->replace($dough, [['componentId' => $flour->id, 'quantity' => 0.5]]);
        $this->recipes()->replace($bread, [['componentId' => $dough->id, 'quantity' => 1]]);

        $exploded = $this->recipes()->explode($bread->fresh());

        // The dough is held as dough, so that is what depletes. Going further
        // would take the flour a second time.
        $this->assertSame('عجينة', $exploded->sole()['item']->name);
    }

    public function test_a_recipe_cannot_contain_itself(): void
    {
        $dish = $this->dish('طبق');

        $this->recipes()->replace($dish, [['componentId' => $dish->id, 'quantity' => 1]]);

        $this->assertSame(0, $dish->fresh()->recipeComponents()->count());
    }

    public function test_an_indirect_loop_is_refused(): void
    {
        $a = $this->dish('أ');
        $b = $this->dish('ب');

        $this->recipes()->replace($a, [['componentId' => $b->id, 'quantity' => 1]]);

        $this->expectException(RuntimeException::class);

        $this->recipes()->replace($b, [['componentId' => $a->id, 'quantity' => 1]]);
    }

    public function test_the_component_picker_hides_anything_that_would_loop(): void
    {
        $a = $this->dish('أ');
        $b = $this->dish('ب');

        $this->recipes()->replace($a, [['componentId' => $b->id, 'quantity' => 1]]);

        $eligible = $this->recipes()->eligibleComponents($b->fresh())->pluck('id')->all();

        $this->assertNotContains($a->id, $eligible);
        $this->assertNotContains($b->id, $eligible);
    }

    public function test_selling_an_assembled_dish_depletes_its_ingredients(): void
    {
        $this->location();

        $bread = $this->ingredient('خبز', 2, 'H87');
        $meat = $this->ingredient('لحمة', 40);
        $burger = $this->dish('برغر');

        $this->stock()->receive($bread, 100, 2);
        $this->stock()->receive($meat, 10, 40);

        $this->recipes()->replace($burger, [
            ['componentId' => $bread->id, 'quantity' => 1],
            ['componentId' => $meat->id, 'quantity' => 0.15],
        ]);

        $this->stock()->issue($burger->fresh(), 3);

        $this->assertSame(97.0, $this->stock()->onHand($bread->id));
        $this->assertSame(9.55, $this->stock()->onHand($meat->id));

        // Nothing is held as a burger, so no burger movement exists.
        $this->assertSame(0, StockMovement::forItem($burger->id)->count());
    }

    public function test_component_draws_are_recorded_as_consumption_not_sales(): void
    {
        $this->location();

        $bread = $this->ingredient('خبز', 2, 'H87');
        $burger = $this->dish('برغر');

        $this->stock()->receive($bread, 10, 2);
        $this->recipes()->replace($burger, [['componentId' => $bread->id, 'quantity' => 1]]);

        $this->stock()->issue($burger->fresh(), 1);

        // What was sold is the order; what left the shelf is bread. Keeping the
        // two apart is what lets a kitchen compare theoretical against actual.
        $movement = StockMovement::forItem($bread->id)->where('quantity', '<', 0)->sole();
        $this->assertSame(StockMovementType::Consumption, $movement->type);
    }

    public function test_a_stocked_finished_item_depletes_itself_not_its_recipe(): void
    {
        $this->location();

        $flour = $this->ingredient('طحين', 3);
        $loaf = $this->dish('رغيف', 2, stocked: true);

        $this->stock()->receive($flour, 100, 3);
        $this->stock()->receive($loaf, 50, 1);

        $this->recipes()->replace($loaf, [['componentId' => $flour->id, 'quantity' => 0.2]]);

        $this->stock()->issue($loaf->fresh(), 10);

        // The flour was already consumed by the production run that made these.
        $this->assertSame(40.0, $this->stock()->onHand($loaf->id));
        $this->assertSame(100.0, $this->stock()->onHand($flour->id));
    }

    public function test_recipes_are_off_for_a_vertical_that_does_not_have_them(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);

        $this->assertFalse(app(RecipeService::class)->enabled());
    }

    public function test_margin_is_null_without_a_cost_to_compare_against(): void
    {
        $free = $this->ingredient('ماء', 0);
        $dish = $this->dish('كوب ماء', 5);

        $this->recipes()->replace($dish, [['componentId' => $free->id, 'quantity' => 1]]);

        $this->assertNull($this->recipes()->theoreticalMargin($dish->fresh()));
    }
}
