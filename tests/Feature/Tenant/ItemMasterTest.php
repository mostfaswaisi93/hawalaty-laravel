<?php

namespace Tests\Feature\Tenant;

use App\Enums\BusinessType;
use App\Models\Tenant\Location;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\MenuService;
use App\Services\Tenant\StockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * The item record, end to end.
 *
 * The catalogue is where every other module gets its facts: the ledger reads the
 * unit, the invoice reads the tax category, dispensing reads the prescription
 * flag. A field the engine honours but nobody can reach is a field that does not
 * exist, so these tests are as much about the screen as about the service.
 */
class ItemMasterTest extends TenantTestCase
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

    private function menu(): MenuService
    {
        return app(MenuService::class);
    }

    private function owner(): TeamMember
    {
        $roles = $this->seedStandardRoles();

        return TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    private function item(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge([
            'menu_category_id' => MenuCategory::factory()->create()->id,
            'name' => 'زيت زيتون',
            'kind' => 'goods',
            'track_stock' => true,
            'unit_code' => 'H87',
            'price' => 100,
            'cost' => 0,
        ], $attributes));
    }

    // -- The fields reach the record ---------------------------------------

    public function test_every_commerce_field_survives_a_round_trip(): void
    {
        $item = $this->item();

        $this->menu()->updateItem($item, [
            'barcode' => '6291041500213',
            'sku' => 'OIL-1L',
            'pluCode' => '4011',
            'unitCode' => 'LTR',
            'purchaseUnitCode' => 'XBX',
            'purchaseConversion' => 12,
            'taxRate' => 15,
            'taxCategory' => 'S',
            'taxIncluded' => true,
            'cost' => 60,
            'reorderPoint' => 24,
            'trackSerials' => false,
            'warrantyMonths' => 24,
            'serviceMinutes' => 45,
            'coreCharge' => 30,
            'isPrescription' => true,
            'controlSchedule' => 'II',
            'atcCode' => 'N02BE01',
            'activeIngredient' => 'Paracetamol',
            'strength' => '500 mg',
            'dosageForm' => 'أقراص',
            'coldChain' => true,
        ]);

        $fresh = $item->fresh();

        $this->assertSame('6291041500213', $fresh->barcode);
        $this->assertSame('OIL-1L', $fresh->sku);
        $this->assertSame('4011', $fresh->plu_code);
        $this->assertSame('LTR', $fresh->unit_code);
        $this->assertSame('XBX', $fresh->purchase_unit_code);
        $this->assertSame('12.000', $fresh->purchase_conversion);
        $this->assertSame('15.00', $fresh->tax_rate);
        $this->assertSame('60.000', $fresh->cost);
        $this->assertSame('24.000', $fresh->reorder_point);
        $this->assertSame(24, $fresh->warranty_months);
        $this->assertSame(45, $fresh->service_minutes);
        $this->assertSame('30.00', $fresh->core_charge);
        $this->assertTrue((bool) $fresh->is_prescription);
        $this->assertSame('II', $fresh->control_schedule);
        $this->assertSame('N02BE01', $fresh->atc_code);
        $this->assertSame('Paracetamol', $fresh->active_ingredient);
        $this->assertSame('500 mg', $fresh->strength);
        $this->assertSame('أقراص', $fresh->dosage_form);
        $this->assertTrue((bool) $fresh->cold_chain);
    }

    public function test_an_emptied_text_field_becomes_null_rather_than_an_empty_string(): void
    {
        $item = $this->item(['barcode' => '123', 'sku' => 'A']);

        $this->menu()->updateItem($item, ['barcode' => '', 'sku' => '   ']);

        // "Not set" and "set to nothing" have to stay distinguishable, or a
        // uniqueness check starts matching every blank against every other.
        $this->assertNull($item->fresh()->barcode);
        $this->assertNull($item->fresh()->sku);
    }

    public function test_fields_left_out_of_the_payload_are_left_alone(): void
    {
        $item = $this->item(['barcode' => '123', 'reorder_point' => 5]);

        $this->menu()->updateItem($item, ['name' => 'اسم جديد']);

        $this->assertSame('اسم جديد', $item->fresh()->name);
        $this->assertSame('123', $item->fresh()->barcode);
        $this->assertSame('5.000', $item->fresh()->reorder_point);
    }

    // -- The guards --------------------------------------------------------

    /**
     * Quantities in the ledger were counted in the old unit. Reinterpreting them
     * turns 5 kilos into 5 pieces without a single row changing.
     */
    public function test_the_unit_cannot_change_once_stock_has_moved(): void
    {
        $item = $this->item(['unit_code' => 'KGM']);
        app(StockService::class)->receive($item, 10, 4);

        $this->expectException(RuntimeException::class);
        $this->menu()->updateItem($item, ['unitCode' => 'H87']);
    }

    public function test_the_unit_can_change_before_anything_has_moved(): void
    {
        $item = $this->item(['unit_code' => 'H87']);

        $this->menu()->updateItem($item, ['unitCode' => 'KGM']);

        $this->assertSame('KGM', $item->fresh()->unit_code);
    }

    /**
     * The cost is the rolled weighted average once anything has moved. Typing
     * over it is an unaudited restatement of what the shop paid.
     */
    public function test_the_cost_is_an_opening_figure_only_until_the_first_movement(): void
    {
        $item = $this->item();

        $this->menu()->updateItem($item, ['cost' => 40]);
        $this->assertSame('40.000', $item->fresh()->cost);

        app(StockService::class)->receive($item->fresh(), 10, 60);

        // Silently ignored rather than refused: the field is not offered on the
        // screen at this point, so a value arriving here is noise, not intent.
        $this->menu()->updateItem($item->fresh(), ['cost' => 5]);
        $this->assertSame('60.000', $item->fresh()->cost);
    }

    public function test_tracking_cannot_be_switched_off_while_stock_is_on_the_shelf(): void
    {
        $item = $this->item();
        app(StockService::class)->receive($item, 3, 10);

        $this->expectException(RuntimeException::class);
        $this->menu()->updateItem($item, ['trackStock' => false]);
    }

    public function test_tracking_can_be_switched_off_once_the_balance_is_zero(): void
    {
        $item = $this->item();
        app(StockService::class)->receive($item, 3, 10);
        app(StockService::class)->waste($item, 3, 'إتلاف');

        $this->menu()->updateItem($item->fresh(), ['trackStock' => false]);

        $this->assertFalse((bool) $item->fresh()->track_stock);
    }

    /**
     * Units already on the shelf have no serials, and an issue of a serialised
     * item without one is refused - so the balance would become unsellable.
     */
    public function test_serial_tracking_cannot_be_switched_on_over_existing_stock(): void
    {
        $this->bindCurrentLicense(BusinessType::Electronics);

        $item = $this->item(['name' => 'جوال']);
        app(StockService::class)->receive($item, 2, 800);

        $this->expectException(RuntimeException::class);
        $this->menu()->updateItem($item, ['trackSerials' => true]);
    }

    public function test_a_moved_item_cannot_become_a_service(): void
    {
        $item = $this->item();
        app(StockService::class)->receive($item, 1, 10);

        $this->expectException(RuntimeException::class);
        $this->menu()->updateItem($item, ['kind' => 'service']);
    }

    // -- The screen --------------------------------------------------------

    public function test_the_item_page_renders_and_shows_the_sections_the_trade_needs(): void
    {
        $item = $this->item();

        $this->actingAs($this->owner(), 'tenant')
            ->get(route('menu.items.edit', $item->id))
            ->assertOk()
            ->assertSee($item->name)
            ->assertSee(__('وحدة القياس'))
            ->assertSee(__('المخزون'))

            // A grocery has no prescriptions, no serials and no appointments.
            ->assertDontSee(__('بيانات الدواء'))
            ->assertDontSee(__('الخدمة'));
    }

    public function test_a_pharmacy_gets_the_medicine_section(): void
    {
        $this->bindCurrentLicense(BusinessType::Pharmacy);
        $item = $this->item(['name' => 'دواء']);

        $this->actingAs($this->owner(), 'tenant')
            ->get(route('menu.items.edit', $item->id))
            ->assertOk()
            ->assertSee(__('بيانات الدواء'))
            ->assertSee(__('يُصرف بوصفة'))
            ->assertSee(__('رمز ATC'));
    }

    public function test_a_services_workspace_gets_neither_units_nor_stock(): void
    {
        $this->bindCurrentLicense(BusinessType::Services);
        $item = $this->item(['kind' => 'service', 'track_stock' => false]);

        $this->actingAs($this->owner(), 'tenant')
            ->get(route('menu.items.edit', $item->id))
            ->assertOk()
            ->assertSee(__('الخدمة'))
            ->assertDontSee(__('وحدة القياس'))
            ->assertDontSee(__('نقطة إعادة الطلب'));
    }

    public function test_the_page_saves_and_returns_to_itself(): void
    {
        $item = $this->item();

        $this->actingAs($this->owner(), 'tenant')
            ->put(route('menu.items.update', $item->id), [
                'name' => $item->name,
                'menuCategoryId' => $item->menu_category_id,
                'price' => 120,
                'barcode' => '6291041500213',
                'reorderPoint' => 12,
                'fromPage' => 1,
            ])
            ->assertRedirect(route('menu.items.edit', $item->id));

        $this->assertSame('6291041500213', $item->fresh()->barcode);
        $this->assertSame('12.000', $item->fresh()->reorder_point);
    }

    public function test_a_refused_change_comes_back_with_the_reason(): void
    {
        $item = $this->item();
        app(StockService::class)->receive($item, 5, 10);

        $this->actingAs($this->owner(), 'tenant')
            ->put(route('menu.items.update', $item->id), [
                'name' => $item->name,
                'menuCategoryId' => $item->menu_category_id,
                'price' => 100,
                'unitCode' => 'KGM',
                'fromPage' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('H87', $item->fresh()->unit_code);
    }

    public function test_a_cashier_cannot_open_the_item_record(): void
    {
        $roles = $this->seedStandardRoles();
        $cashier = TeamMember::factory()->create(['role_id' => $roles['staff']->id]);

        $this->actingAs($cashier, 'tenant')
            ->get(route('menu.items.edit', $this->item()->id))
            ->assertRedirect('/dashboard');
    }

    // -- Validation --------------------------------------------------------

    public function test_a_barcode_cannot_name_two_items(): void
    {
        $this->item(['name' => 'أ', 'barcode' => '6291041500213']);
        $other = $this->item(['name' => 'ب']);

        $this->actingAs($this->owner(), 'tenant')
            ->put(route('menu.items.update', $other->id), [
                'name' => 'ب',
                'menuCategoryId' => $other->menu_category_id,
                'price' => 10,
                'barcode' => '6291041500213',
            ])
            ->assertSessionHasErrors('barcode');
    }

    public function test_an_item_keeps_its_own_barcode_on_save(): void
    {
        $item = $this->item(['barcode' => '6291041500213']);

        $this->actingAs($this->owner(), 'tenant')
            ->put(route('menu.items.update', $item->id), [
                'name' => $item->name,
                'menuCategoryId' => $item->menu_category_id,
                'price' => 10,
                'barcode' => '6291041500213',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_a_malformed_atc_code_is_refused(): void
    {
        $item = $this->item();

        $this->actingAs($this->owner(), 'tenant')
            ->put(route('menu.items.update', $item->id), [
                'name' => $item->name,
                'menuCategoryId' => $item->menu_category_id,
                'price' => 10,
                'atcCode' => 'NOT-A-CODE',
            ])
            ->assertSessionHasErrors('atcCode');
    }

    public function test_an_invented_unit_code_is_refused(): void
    {
        $item = $this->item();

        $this->actingAs($this->owner(), 'tenant')
            ->put(route('menu.items.update', $item->id), [
                'name' => $item->name,
                'menuCategoryId' => $item->menu_category_id,
                'price' => 10,
                'unitCode' => 'BANANAS',
            ])
            ->assertSessionHasErrors('unitCode');
    }

    // -- CSV ---------------------------------------------------------------

    public function test_an_exported_file_can_be_imported_back_unchanged(): void
    {
        $this->item([
            'name' => 'زيت',
            'barcode' => '6291041500213',
            'sku' => 'OIL',
            'unit_code' => 'LTR',
            'cost' => 60,
            'tax_rate' => 15,
            'tax_category' => 'S',
            'reorder_point' => 24,
        ]);

        $rows = $this->menu()->exportRows();
        $csv = implode(',', MenuService::CSV_COLUMNS)."\n";

        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $row))."\n";
        }

        $result = $this->menu()->importCsv(UploadedFile::fake()->createWithContent('menu.csv', $csv));

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['skipped']);

        $fresh = MenuItem::where('name', 'زيت')->firstOrFail();

        $this->assertSame('6291041500213', $fresh->barcode);
        $this->assertSame('LTR', $fresh->unit_code);
        $this->assertSame('24.000', $fresh->reorder_point);
        $this->assertSame('15.00', $fresh->tax_rate);
    }

    public function test_a_narrow_file_leaves_the_columns_it_omits_alone(): void
    {
        $this->item(['name' => 'زيت', 'barcode' => '6291041500213', 'reorder_point' => 24, 'track_stock' => true]);

        $csv = "category,name,price\n";
        $csv .= '"'.MenuCategory::first()->name."\",زيت,150\n";

        $this->menu()->importCsv(UploadedFile::fake()->createWithContent('menu.csv', $csv));

        $fresh = MenuItem::where('name', 'زيت')->firstOrFail();

        $this->assertSame('150.000', $fresh->price);
        $this->assertSame('6291041500213', $fresh->barcode);
        $this->assertSame('24.000', $fresh->reorder_point);
        $this->assertTrue((bool) $fresh->track_stock);
    }

    public function test_a_spreadsheet_cannot_overwrite_a_cost_the_ledger_owns(): void
    {
        $item = $this->item(['name' => 'زيت']);
        app(StockService::class)->receive($item, 10, 60);

        $csv = "category,name,price,cost\n";
        $csv .= '"'.MenuCategory::first()->name."\",زيت,150,5\n";

        $this->menu()->importCsv(UploadedFile::fake()->createWithContent('menu.csv', $csv));

        $this->assertSame('60.000', $item->fresh()->cost);
    }
}
