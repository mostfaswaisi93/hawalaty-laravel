<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\MenuService;
use Illuminate\Http\UploadedFile;
use Tests\TenantTestCase;

class MenuServiceTest extends TenantTestCase
{
    private array $tempCsvFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempCsvFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function csvUploadedFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);
        $this->tempCsvFiles[] = $path;

        return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
    }

    public function test_create_category_assigns_sequential_positions(): void
    {
        $service = app(MenuService::class);

        $first = $service->createCategory(['name' => 'مقبلات']);
        $second = $service->createCategory(['name' => 'مشروبات']);
        $third = $service->createCategory(['name' => 'حلويات']);

        $this->assertSame(1, $first->position);
        $this->assertSame(2, $second->position);
        $this->assertSame(3, $third->position);
    }

    public function test_create_category_continues_from_existing_max_position(): void
    {
        MenuCategory::factory()->create(['position' => 5]);
        MenuCategory::factory()->create(['position' => 8]);

        $category = app(MenuService::class)->createCategory(['name' => 'أطباق رئيسية']);

        $this->assertSame(9, $category->position);
    }

    public function test_list_categories_orders_by_position(): void
    {
        MenuCategory::factory()->create(['name' => 'C', 'position' => 3]);
        MenuCategory::factory()->create(['name' => 'A', 'position' => 1]);
        MenuCategory::factory()->create(['name' => 'B', 'position' => 2]);

        $names = app(MenuService::class)->listCategories()->pluck('name')->all();

        $this->assertSame(['A', 'B', 'C'], $names);
    }

    public function test_delete_category_cascades_to_its_items(): void
    {
        $category = MenuCategory::factory()->create();
        $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);

        app(MenuService::class)->deleteCategory($category);

        $this->assertNull(MenuCategory::find($category->id));

        $this->assertNull(MenuItem::find($item->id));
    }

    public function test_create_item_persists_expected_attributes(): void
    {
        $category = MenuCategory::factory()->create();

        $item = app(MenuService::class)->createItem([
            'menuCategoryId' => $category->id,
            'name' => 'شاورما دجاج',
            'price' => '12.50',
        ]);

        $this->assertSame('شاورما دجاج', $item->name);
        $this->assertSame('12.50', $item->price);
        $this->assertTrue($item->active);
        $this->assertTrue($item->relationLoaded('menuCategory'));
        $this->assertSame($category->id, $item->menuCategory->id);
    }

    public function test_update_item_only_touches_keys_present_in_data(): void
    {
        $item = MenuItem::factory()->create(['name' => 'مسخن', 'price' => '10.00']);

        $updated = app(MenuService::class)->updateItem($item, ['price' => '20.00']);

        $this->assertSame('20.00', $updated->price);
        $this->assertSame('مسخن', $updated->name);
    }

    public function test_delete_item_removes_it(): void
    {
        $item = MenuItem::factory()->create();

        app(MenuService::class)->deleteItem($item);

        $this->assertNull(MenuItem::find($item->id));
    }

    public function test_list_items_filters_by_name_search(): void
    {
        MenuItem::factory()->create(['name' => 'شاورما دجاج']);
        MenuItem::factory()->create(['name' => 'شاورما لحم']);
        MenuItem::factory()->create(['name' => 'عصير برتقال']);

        $names = app(MenuService::class)->listItems('شاورما')->pluck('name')->all();

        sort($names);
        $this->assertSame(['شاورما دجاج', 'شاورما لحم'], $names);
    }

    public function test_list_items_eager_loads_menu_category(): void
    {
        MenuItem::factory()->create();

        $item = app(MenuService::class)->listItems()->first();

        $this->assertTrue($item->relationLoaded('menuCategory'));
    }

    public function test_export_rows_ordered_by_category_position_then_item_name(): void
    {
        $catA = MenuCategory::factory()->create(['name' => 'Cat A', 'position' => 1]);
        $catB = MenuCategory::factory()->create(['name' => 'Cat B', 'position' => 2]);

        MenuItem::factory()->create(['menu_category_id' => $catA->id, 'name' => 'Zebra']);
        MenuItem::factory()->create(['menu_category_id' => $catA->id, 'name' => 'Apple']);
        MenuItem::factory()->create(['menu_category_id' => $catB->id, 'name' => 'Mango']);

        $rows = app(MenuService::class)->exportRows();

        $this->assertSame([
            ['Cat A', 'Apple'],
            ['Cat A', 'Zebra'],
            ['Cat B', 'Mango'],
        ], array_map(fn ($row) => [$row['category'], $row['name']], $rows));
    }

    public function test_export_sanitizes_names_starting_with_formula_trigger_characters(): void
    {
        $category = MenuCategory::factory()->create(['name' => '=CategoryFormula', 'position' => 1]);
        $dangerousNames = ['=SUM(A1:A9)', '+1;calc', '-cmd.exe', '@import', "\tTabbed", "\rCarriageReturn"];

        foreach ($dangerousNames as $name) {
            MenuItem::factory()->create(['menu_category_id' => $category->id, 'name' => $name]);
        }

        $rows = app(MenuService::class)->exportRows();
        $exportedNames = array_column($rows, 'name');

        $this->assertContains("'=CategoryFormula", array_column($rows, 'category'));

        foreach ($dangerousNames as $name) {
            $this->assertContains("'".$name, $exportedNames);
        }
    }

    public function test_export_leaves_normal_names_unchanged(): void
    {
        $category = MenuCategory::factory()->create(['name' => 'مشروبات', 'position' => 1]);
        MenuItem::factory()->create(['menu_category_id' => $category->id, 'name' => 'مسخن']);

        $rows = app(MenuService::class)->exportRows();

        $this->assertSame('مشروبات', $rows[0]['category']);
        $this->assertSame('مسخن', $rows[0]['name']);
    }

    public function test_export_active_as_string(): void
    {
        $category = MenuCategory::factory()->create(['position' => 1]);
        MenuItem::factory()->create(['menu_category_id' => $category->id, 'name' => 'Active Item', 'active' => true]);
        MenuItem::factory()->create(['menu_category_id' => $category->id, 'name' => 'Inactive Item', 'active' => false]);

        $rows = collect(app(MenuService::class)->exportRows())->keyBy('name');

        $this->assertSame('1', $rows['Active Item']['active']);
        $this->assertSame('0', $rows['Inactive Item']['active']);
    }

    public function test_import_creates_new_category_and_item(): void
    {
        $csv = "category,name,price,active\nمقبلات,حمص,15.50,1\n";

        $result = app(MenuService::class)->importCsv($this->csvUploadedFile($csv));

        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $result);

        $category = MenuCategory::where('name', 'مقبلات')->first();
        $this->assertNotNull($category);

        $item = MenuItem::where('name', 'حمص')->first();
        $this->assertNotNull($item);
        $this->assertSame($category->id, $item->menu_category_id);
        $this->assertSame('15.50', $item->price);
        $this->assertTrue($item->active);
    }

    public function test_import_marks_item_inactive_when_active_column_is_zero(): void
    {
        $csv = "category,name,price,active\nمقبلات,حمص,15.50,0\n";

        app(MenuService::class)->importCsv($this->csvUploadedFile($csv));

        $this->assertFalse(MenuItem::where('name', 'حمص')->first()->active);
    }

    public function test_import_updates_existing_item_matched_by_category_and_name(): void
    {
        $category = MenuCategory::factory()->create(['name' => 'مقبلات', 'position' => 1]);
        $item = MenuItem::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'حمص',
            'price' => '10.00',
        ]);

        $csv = "category,name,price,active\n".
            "مقبلات,حمص,20.00,1\n".
            "مقبلات,فتوش,8.00,1\n";

        $result = app(MenuService::class)->importCsv($this->csvUploadedFile($csv));

        $this->assertSame(['created' => 1, 'updated' => 1, 'skipped' => 0], $result);
        $this->assertSame(2, MenuItem::count());
        $this->assertSame('20.00', $item->fresh()->price);
        $this->assertNotNull(MenuItem::where('name', 'فتوش')->first());
    }

    public function test_import_rejects_negative_price(): void
    {
        $csv = "category,name,price,active\nمقبلات,سلطة,-5,1\n";

        $result = app(MenuService::class)->importCsv($this->csvUploadedFile($csv));

        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 1], $result);
        $this->assertNull(MenuItem::where('name', 'سلطة')->first());
        $this->assertNull(MenuCategory::where('name', 'مقبلات')->first());
    }

    public function test_import_rejects_non_numeric_price(): void
    {
        $csv = "category,name,price,active\nمقبلات,سلطة,abc,1\n";

        $result = app(MenuService::class)->importCsv($this->csvUploadedFile($csv));

        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 1], $result);
        $this->assertNull(MenuItem::where('name', 'سلطة')->first());
    }

    public function test_import_skips_rows_missing_category_or_name(): void
    {
        $csv = "category,name,price,active\n".
            ",سلطة,10,1\n".
            "مقبلات,,10,1\n";

        $result = app(MenuService::class)->importCsv($this->csvUploadedFile($csv));

        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 2], $result);
        $this->assertSame(0, MenuItem::count());
    }

    public function test_import_auto_creates_category_appended_after_max_position(): void
    {
        MenuCategory::factory()->create(['position' => 5]);

        $csv = "category,name,price,active\nNewCat,Item1,10.00,1\n";

        app(MenuService::class)->importCsv($this->csvUploadedFile($csv));

        $newCategory = MenuCategory::where('name', 'NewCat')->first();
        $this->assertNotNull($newCategory);
        $this->assertSame(6, $newCategory->position);
    }

    public function test_import_header_is_case_insensitive(): void
    {
        $csv = "CATEGORY,NAME,PRICE,ACTIVE\nمقبلات,حمص,15.50,1\n";

        $result = app(MenuService::class)->importCsv($this->csvUploadedFile($csv));

        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0], $result);
    }
}
