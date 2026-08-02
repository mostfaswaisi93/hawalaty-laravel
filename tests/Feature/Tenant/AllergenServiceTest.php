<?php

namespace Tests\Feature\Tenant;

use App\Enums\Allergen;
use App\Enums\AllergenPresence;
use App\Enums\BusinessType;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\AllergenService;
use App\Services\Tenant\RecipeService;
use Tests\TenantTestCase;

class AllergenServiceTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindCurrentLicense(BusinessType::Restaurant);
    }

    private function allergens(): AllergenService
    {
        return app(AllergenService::class);
    }

    private function recipes(): RecipeService
    {
        return app(RecipeService::class);
    }

    private function item(string $name, bool $stocked = true): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => $name,
            'kind' => 'goods',
            'track_stock' => $stocked,
        ]);
    }

    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    public function test_the_regulation_list_is_complete(): void
    {
        // Annex II of EU 1169/2011 names exactly fourteen.
        $this->assertCount(14, Allergen::cases());

        foreach (Allergen::cases() as $allergen) {
            $this->assertNotSame('', $allergen->label());
            $this->assertNotSame('', $allergen->note());
        }
    }

    public function test_a_declaration_records_that_the_item_was_checked(): void
    {
        $flour = $this->item('طحين');

        $this->assertFalse($flour->allergensReviewed());

        $this->allergens()->declare($flour, [Allergen::Gluten->value => AllergenPresence::Contains->value]);

        $this->assertTrue($flour->fresh()->allergensReviewed());
    }

    public function test_declaring_nothing_still_counts_as_having_checked(): void
    {
        $water = $this->item('ماء');

        $this->allergens()->declare($water, []);

        // "We looked, there are none" is an answer. "Nobody looked" is a gap.
        // An empty declaration list alone cannot tell them apart.
        $this->assertTrue($water->fresh()->allergensReviewed());
        $this->assertTrue($this->allergens()->declared($water->fresh())->isEmpty());
    }

    public function test_a_dish_inherits_from_its_ingredients(): void
    {
        $flour = $this->item('طحين');
        $milk = $this->item('حليب');
        $cake = $this->item('كيك', stocked: false);

        $this->allergens()->declare($flour, [Allergen::Gluten->value => AllergenPresence::Contains->value]);
        $this->allergens()->declare($milk, [Allergen::Milk->value => AllergenPresence::Contains->value]);

        $this->recipes()->replace($cake, [
            ['componentId' => $flour->id, 'quantity' => 0.2],
            ['componentId' => $milk->id, 'quantity' => 0.1],
        ]);

        $effective = $this->allergens()->effective($cake->fresh());

        $this->assertTrue($effective->has(Allergen::Gluten->value));
        $this->assertTrue($effective->has(Allergen::Milk->value));
    }

    /**
     * The distinction from stock explosion, which stops at anything stocked.
     */
    public function test_inheritance_passes_through_a_stocked_intermediate(): void
    {
        $flour = $this->item('طحين');
        $dough = $this->item('عجينة', stocked: true);
        $bread = $this->item('رغيف', stocked: false);

        $this->allergens()->declare($flour, [Allergen::Gluten->value => AllergenPresence::Contains->value]);

        $this->recipes()->replace($dough, [['componentId' => $flour->id, 'quantity' => 0.5]]);
        $this->recipes()->replace($bread, [['componentId' => $dough->id, 'quantity' => 1]]);

        // Depletion stops at the dough because that is what is counted. Gluten
        // does not stop there - the bread still contains it.
        $this->assertSame(['عجينة'], $this->recipes()->explode($bread->fresh())->pluck('item.name')->all());
        $this->assertTrue($this->allergens()->effective($bread->fresh())->has(Allergen::Gluten->value));
    }

    public function test_a_deliberate_ingredient_outranks_a_trace(): void
    {
        $a = $this->item('مكوّن أ');
        $b = $this->item('مكوّن ب');
        $dish = $this->item('طبق', stocked: false);

        $this->allergens()->declare($a, [Allergen::Nuts->value => AllergenPresence::MayContain->value]);
        $this->allergens()->declare($b, [Allergen::Nuts->value => AllergenPresence::Contains->value]);

        $this->recipes()->replace($dish, [
            ['componentId' => $a->id, 'quantity' => 1],
            ['componentId' => $b->id, 'quantity' => 1],
        ]);

        // Softening a definite ingredient to "may contain" would understate it.
        $this->assertSame(
            AllergenPresence::Contains,
            $this->allergens()->effective($dish->fresh())->get(Allergen::Nuts->value),
        );
    }

    public function test_a_trace_does_not_get_promoted_on_its_own(): void
    {
        $nutsy = $this->item('مكوّن');
        $dish = $this->item('طبق', stocked: false);

        $this->allergens()->declare($nutsy, [Allergen::Nuts->value => AllergenPresence::MayContain->value]);
        $this->recipes()->replace($dish, [['componentId' => $nutsy->id, 'quantity' => 1]]);

        $this->assertSame(
            AllergenPresence::MayContain,
            $this->allergens()->effective($dish->fresh())->get(Allergen::Nuts->value),
        );
    }

    public function test_the_label_marks_what_was_inherited(): void
    {
        $flour = $this->item('طحين');
        $dish = $this->item('طبق', stocked: false);

        $this->allergens()->declare($flour, [Allergen::Gluten->value => AllergenPresence::Contains->value]);
        $this->allergens()->declare($dish, [Allergen::Eggs->value => AllergenPresence::Contains->value]);
        $this->recipes()->replace($dish, [['componentId' => $flour->id, 'quantity' => 1]]);

        $label = $this->allergens()->labelFor($dish->fresh())->keyBy(fn ($row) => $row['allergen']->value);

        $this->assertFalse($label[Allergen::Eggs->value]['inherited']);
        $this->assertTrue($label[Allergen::Gluten->value]['inherited']);
    }

    public function test_the_label_reads_in_the_regulation_order(): void
    {
        $dish = $this->item('طبق');

        $this->allergens()->declare($dish, [
            Allergen::Sesame->value => AllergenPresence::Contains->value,
            Allergen::Gluten->value => AllergenPresence::Contains->value,
            Allergen::Milk->value => AllergenPresence::Contains->value,
        ]);

        // Declared out of order, listed in Annex II order so two items always
        // read the same way.
        $this->assertSame(
            [Allergen::Gluten->value, Allergen::Milk->value, Allergen::Sesame->value],
            $this->allergens()->labelFor($dish->fresh())->pluck('allergen')->map->value->all(),
        );
    }

    public function test_the_bulk_resolver_agrees_with_the_single_one(): void
    {
        $flour = $this->item('طحين');
        $dough = $this->item('عجينة');
        $bread = $this->item('رغيف', stocked: false);

        $this->allergens()->declare($flour, [Allergen::Gluten->value => AllergenPresence::Contains->value]);
        $this->recipes()->replace($dough, [['componentId' => $flour->id, 'quantity' => 1]]);
        $this->recipes()->replace($bread, [['componentId' => $dough->id, 'quantity' => 1]]);

        $items = MenuItem::with('allergenDeclarations')->get();
        $bulk = $this->allergens()->labelsFor($items);

        foreach ($items as $item) {
            $this->assertEquals(
                $this->allergens()->labelFor($item)->toArray(),
                $bulk->get($item->id)->toArray(),
                "Bulk and single resolvers disagree on {$item->name}.",
            );
        }
    }

    public function test_a_recipe_loop_cannot_hang_the_resolver(): void
    {
        $a = $this->item('أ', stocked: false);
        $b = $this->item('ب', stocked: false);

        $this->allergens()->declare($a, [Allergen::Milk->value => AllergenPresence::Contains->value]);

        // Written straight to the table so the service's own cycle guard is not
        // what is being tested here - the resolver has to survive bad data too.
        $a->recipeComponents()->create(['component_item_id' => $b->id, 'quantity' => 1]);
        $b->recipeComponents()->create(['component_item_id' => $a->id, 'quantity' => 1]);

        $this->assertTrue($this->allergens()->effective($b->fresh())->has(Allergen::Milk->value));
        $this->assertTrue($this->allergens()->labelsFor(MenuItem::all())->has($b->id));
    }

    public function test_unreviewed_items_are_listed(): void
    {
        $checked = $this->item('مفحوص');
        $this->item('غير مفحوص');

        $this->allergens()->declare($checked, []);

        $this->assertSame(['غير مفحوص'], $this->allergens()->unreviewed()->pluck('name')->all());
    }

    public function test_a_dish_resting_entirely_on_unchecked_ingredients_is_flagged(): void
    {
        $mystery = $this->item('مكوّن مجهول');
        $dish = $this->item('طبق', stocked: false);

        $this->allergens()->declare($dish, []);
        $this->recipes()->replace($dish, [['componentId' => $mystery->id, 'quantity' => 1]]);

        // The dish looks answered - an empty, confident label built on an
        // ingredient nobody examined.
        $this->assertSame(['طبق'], $this->allergens()->restingOnUnreviewedComponents()->pluck('name')->all());

        $this->allergens()->declare($mystery, []);

        $this->assertTrue($this->allergens()->restingOnUnreviewedComponents()->isEmpty());
    }

    public function test_the_screens_render_and_save(): void
    {
        $owner = $this->owner();
        $flour = $this->item('طحين');

        $this->actingAs($owner, 'tenant')->get('/allergens')->assertOk()->assertSee('طحين');
        $this->actingAs($owner, 'tenant')->get("/allergens/{$flour->id}")->assertOk();

        $this->actingAs($owner, 'tenant')
            ->put("/allergens/{$flour->id}", [
                'declarations' => [Allergen::Gluten->value => AllergenPresence::Contains->value],
            ])
            ->assertRedirect();

        $this->assertTrue($this->allergens()->declared($flour->fresh())->has(Allergen::Gluten->value));
    }

    public function test_the_screens_are_closed_to_a_vertical_without_allergens(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);

        $this->assertFalse(app(AllergenService::class)->enabled());
        $this->actingAs($this->owner(), 'tenant')->get('/allergens')->assertForbidden();
    }
}
