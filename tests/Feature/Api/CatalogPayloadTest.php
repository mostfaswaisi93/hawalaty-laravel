<?php

namespace Tests\Feature\Api;

use App\Enums\BusinessType;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * What a device is told about the catalogue.
 *
 * This payload is the whole of what an offline till knows about a product. A
 * client that has to come back online to find out it may not sell something is
 * not an offline client, so every fact the till acts on has to be in here.
 */
class CatalogPayloadTest extends TenantApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindCurrentLicense(BusinessType::Grocery);
        Notification::fake();
    }

    private function item(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => 'زيت زيتون',
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'LTR',
            'price' => 115,
            'cost' => 70,
            'tax_rate' => 15,
            'tax_included' => true,
            'tax_category' => 'S',
        ], $attributes));
    }

    private function rows(): Collection
    {
        $this->actAsOwner();

        return collect($this->getJson('/api/v1/menu-items')->assertOk()->json('data'));
    }

    public function test_the_payload_carries_what_a_till_has_to_decide_without_asking(): void
    {
        $item = $this->item(['barcode' => '6291041500213', 'sku' => 'OIL-1L', 'plu_code' => '4011']);

        $row = $this->rows()->firstWhere('id', $item->id);

        $this->assertSame('LTR', $row['unitCode']);
        $this->assertSame('6291041500213', $row['barcode']);
        $this->assertSame('OIL-1L', $row['sku']);
        $this->assertSame('4011', $row['pluCode']);
        // JSON collapses 15.0 to 15, so compare as a number rather than a type.
        $this->assertSame(15.0, (float) $row['tax']['rate']);
        $this->assertSame('S', $row['tax']['category']);
        $this->assertTrue($row['tax']['included']);
        $this->assertTrue($row['trackStock']);
        $this->assertFalse($row['trackSerials']);
        $this->assertFalse($row['assembledToOrder']);
    }

    /**
     * Drives whether the till asks for a weight or a count.
     */
    public function test_the_unit_says_whether_a_quantity_can_be_fractional(): void
    {
        $litres = $this->item(['name' => 'زيت', 'unit_code' => 'LTR']);
        $pieces = $this->item(['name' => 'علبة', 'unit_code' => 'H87']);

        $rows = $this->rows();

        $this->assertTrue($rows->firstWhere('id', $litres->id)['fractional']);
        $this->assertFalse($rows->firstWhere('id', $pieces->id)['fractional']);
    }

    /**
     * A payload saying `prescription: null` to a grocery invites a client to
     * render a field that means nothing there.
     */
    public function test_the_medicine_block_appears_only_where_it_means_something(): void
    {
        $plain = $this->item(['name' => 'سكر']);
        $drug = $this->item([
            'name' => 'دواء',
            'is_prescription' => true,
            'control_schedule' => 'II',
            'cold_chain' => true,
        ]);

        $rows = $this->rows();

        $this->assertArrayNotHasKey('prescription', $rows->firstWhere('id', $plain->id));

        $block = $rows->firstWhere('id', $drug->id)['prescription'];

        $this->assertTrue($block['required']);
        $this->assertSame('II', $block['controlSchedule']);
        $this->assertTrue($block['coldChain']);
    }

    public function test_warranty_and_service_appear_only_when_set(): void
    {
        $plain = $this->item(['name' => 'سكر']);
        $device = $this->item(['name' => 'جوال', 'warranty_months' => 12]);
        $service = $this->item(['name' => 'قصّة شعر', 'kind' => 'service', 'track_stock' => false, 'service_minutes' => 30]);

        $rows = $this->rows();

        $this->assertArrayNotHasKey('warrantyMonths', $rows->firstWhere('id', $plain->id));
        $this->assertSame(12, $rows->firstWhere('id', $device->id)['warrantyMonths']);
        $this->assertSame(30, $rows->firstWhere('id', $service->id)['serviceMinutes']);
    }

    /**
     * A dish is assembled at the till and never stocked as itself, so a client
     * showing its balance would be showing a number that is always zero.
     */
    public function test_an_assembled_dish_is_flagged_as_such(): void
    {
        $this->bindCurrentLicense(BusinessType::Restaurant);

        $flour = $this->item(['name' => 'طحين', 'unit_code' => 'KGM']);
        $dish = $this->item(['name' => 'مناقيش', 'track_stock' => false]);
        $dish->recipeComponents()->create(['component_item_id' => $flour->id, 'quantity' => 0.2]);

        $this->assertTrue($this->rows()->firstWhere('id', $dish->id)['assembledToOrder']);
    }

    public function test_a_variant_names_its_parent(): void
    {
        $parent = $this->item(['name' => 'قميص']);
        $variant = $this->item(['name' => 'قميص - أزرق', 'parent_item_id' => $parent->id]);

        $row = $this->rows()->firstWhere('id', $variant->id);

        $this->assertTrue($row['isVariant']);
        $this->assertSame($parent->id, $row['parentId']);
    }
}
