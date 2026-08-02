<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Models\Tenant\ConstantList;
use App\Models\Tenant\ConstantListItem;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\StockService;
use App\Services\Tenant\VariantService;
use RuntimeException;
use Tests\TenantTestCase;

class VariantServiceTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindCurrentLicense(BusinessType::Clothing);
    }

    private function variants(): VariantService
    {
        return app(VariantService::class);
    }

    private function style(string $name = 'قميص قطن'): MenuItem
    {
        return MenuItem::factory()->create([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => $name,
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'H87',
            'price' => 80,
        ]);
    }

    /**
     * An axis is a constant list; its values are the list's items.
     *
     * @param  list<string>  $values
     * @return array{0: ConstantList, 1: list<string>}
     */
    private function axis(string $title, array $values): array
    {
        $list = ConstantList::create(['key' => md5($title), 'title' => $title]);

        $ids = [];

        foreach ($values as $position => $value) {
            $ids[] = ConstantListItem::create([
                'constant_list_id' => $list->id,
                'value' => $value,
                'position' => $position,
            ])->id;
        }

        return [$list, $ids];
    }

    private function location(): Location
    {
        return Location::default() ?? Location::create([
            'name' => 'Shop', 'kind' => 'store', 'is_default' => true, 'active' => true,
        ]);
    }

    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    public function test_the_matrix_is_the_product_of_its_axes(): void
    {
        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['S', 'M', 'L']);
        [$colours, $colourIds] = $this->axis('الألوان', ['أحمر', 'أزرق']);

        $result = $this->variants()->generate($style, [
            $sizes->id => $sizeIds,
            $colours->id => $colourIds,
        ]);

        // 3 x 2
        $this->assertSame(6, $result['created']);
        $this->assertSame(6, $style->fresh()->variants()->count());
    }

    public function test_a_variant_is_a_real_item_so_the_rest_of_the_system_works_on_it(): void
    {
        $this->location();

        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['M']);

        $this->variants()->generate($style, [$sizes->id => $sizeIds]);

        $variant = $style->fresh()->variants()->sole();

        // The stock ledger, purchases and sales all key on menu_item_id, and a
        // variant being one is what lets every module built so far reach it.
        app(StockService::class)->receive($variant, 10, 40);

        $this->assertSame(10.0, app(StockService::class)->onHand($variant->id));
        $this->assertTrue($variant->isVariant());
        $this->assertSame($style->id, $variant->parent_item_id);
    }

    public function test_the_variant_name_carries_its_coordinates(): void
    {
        $style = $this->style('قميص');
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['M']);
        [$colours, $colourIds] = $this->axis('الألوان', ['أحمر']);

        $this->variants()->generate($style, [$sizes->id => $sizeIds, $colours->id => $colourIds]);

        $this->assertSame('قميص - M / أحمر', $style->fresh()->variants()->sole()->name);
    }

    public function test_generating_again_adds_only_what_is_missing(): void
    {
        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['S', 'M']);
        [$colours, $colourIds] = $this->axis('الألوان', ['أحمر']);

        $this->variants()->generate($style, [$sizes->id => $sizeIds, $colours->id => $colourIds]);

        [, $extraColour] = $this->axisAppend($colours, 'أزرق');

        $result = $this->variants()->generate($style->fresh(), [
            $sizes->id => $sizeIds,
            $colours->id => [...$colourIds, $extraColour],
        ]);

        // A shop adding a colour in March must not lose January's stock history.
        $this->assertSame(2, $result['created']);
        $this->assertSame(2, $result['existing']);
        $this->assertSame(4, $style->fresh()->variants()->count());
    }

    public function test_the_style_stops_being_stocked_once_it_has_variants(): void
    {
        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['M']);

        $this->assertTrue($style->track_stock);

        $this->variants()->generate($style, [$sizes->id => $sizeIds]);

        // A container is not a product; whatever it was before, it holds nothing
        // of its own now.
        $this->assertFalse($style->fresh()->track_stock);
        $this->assertTrue($style->fresh()->isVariantParent());
    }

    public function test_a_style_is_not_sellable_but_its_variants_are(): void
    {
        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['S', 'M']);

        $this->variants()->generate($style, [$sizes->id => $sizeIds]);

        $sellable = MenuItem::query()->sellable()->pluck('id')->all();

        $this->assertNotContains($style->id, $sellable);

        foreach ($style->fresh()->variants as $variant) {
            $this->assertContains($variant->id, $sellable);
        }
    }

    public function test_a_style_is_kept_out_of_the_inventory_listing(): void
    {
        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['M']);

        $this->variants()->generate($style, [$sizes->id => $sizeIds]);

        $this->assertNotContains($style->id, MenuItem::query()->stocked()->pluck('id')->all());
    }

    public function test_variants_cannot_be_derived_from_a_variant(): void
    {
        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['M']);

        $this->variants()->generate($style, [$sizes->id => $sizeIds]);

        $variant = $style->fresh()->variants()->sole();

        $this->expectException(RuntimeException::class);

        $this->variants()->generate($variant, [$sizes->id => $sizeIds]);
    }

    public function test_an_absurd_matrix_is_refused(): void
    {
        $style = $this->style();
        [$a, $aIds] = $this->axis('محور أ', range(1, 25));
        [$b, $bIds] = $this->axis('محور ب', range(1, 25));

        // 625 combinations is a mistake with the pickers, not a decision.
        $this->expectException(RuntimeException::class);

        $this->variants()->generate($style, [$a->id => $aIds, $b->id => $bIds]);
    }

    public function test_a_variant_that_never_moved_stock_can_be_removed(): void
    {
        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['S', 'M']);

        $this->variants()->generate($style, [$sizes->id => $sizeIds]);

        $variant = $style->fresh()->variants()->first();
        $this->variants()->remove($variant);

        $this->assertSame(1, $style->fresh()->variants()->count());
    }

    public function test_a_variant_with_history_cannot_be_removed(): void
    {
        $this->location();

        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['M']);

        $this->variants()->generate($style, [$sizes->id => $sizeIds]);

        $variant = $style->fresh()->variants()->sole();
        app(StockService::class)->receive($variant, 5, 40);

        // Deleting it would orphan movements a valuation has already counted.
        $this->expectException(RuntimeException::class);

        $this->variants()->remove($variant);
    }

    public function test_the_grid_places_each_variant_on_its_square(): void
    {
        $this->location();

        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['S', 'M']);
        [$colours, $colourIds] = $this->axis('الألوان', ['أحمر', 'أزرق']);

        $this->variants()->generate($style, [$sizes->id => $sizeIds, $colours->id => $colourIds]);

        $target = $style->fresh()->variants->firstWhere('name', 'قميص قطن - M / أزرق');
        app(StockService::class)->receive($target, 7, 40);

        $grid = $this->variants()->grid($style->fresh());

        $this->assertCount(2, $grid['rows']);
        $this->assertCount(2, $grid['columns']);

        $key = $this->variants()->cellKey($sizeIds[1], $colourIds[1]);

        $this->assertSame(7.0, $grid['cells'][$key]['on_hand']);
        $this->assertSame($target->id, $grid['cells'][$key]['variant']->id);
    }

    public function test_the_grid_omits_axis_values_nothing_was_made_in(): void
    {
        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['S', 'M', 'L', 'XL']);

        // Only two of the four sizes were generated.
        $this->variants()->generate($style, [$sizes->id => [$sizeIds[0], $sizeIds[1]]]);

        $this->assertCount(2, $this->variants()->grid($style->fresh())['rows']);
    }

    public function test_the_screens_render_and_generate(): void
    {
        $owner = $this->owner();
        $style = $this->style();
        [$sizes, $sizeIds] = $this->axis('المقاسات', ['S', 'M']);

        $this->actingAs($owner, 'tenant')->get('/variants')->assertOk();
        $this->actingAs($owner, 'tenant')->get("/variants/{$style->id}")->assertOk();

        $this->actingAs($owner, 'tenant')
            ->post("/variants/{$style->id}/generate", ['axes' => [$sizes->id => $sizeIds]])
            ->assertRedirect();

        $this->assertSame(2, $style->fresh()->variants()->count());

        $this->actingAs($owner, 'tenant')->get('/variants')->assertOk()->assertSee('قميص قطن');
    }

    public function test_the_screens_are_closed_to_a_vertical_without_variants(): void
    {
        $this->bindCurrentLicense(BusinessType::Grocery);

        $this->assertFalse(app(VariantService::class)->enabled());
        $this->actingAs($this->owner(), 'tenant')->get('/variants')->assertForbidden();
    }

    /**
     * @return array{0: ConstantList, 1: string}
     */
    private function axisAppend(ConstantList $list, string $value): array
    {
        $item = ConstantListItem::create([
            'constant_list_id' => $list->id,
            'value' => $value,
            'position' => $list->items()->count(),
        ]);

        return [$list, $item->id];
    }
}
