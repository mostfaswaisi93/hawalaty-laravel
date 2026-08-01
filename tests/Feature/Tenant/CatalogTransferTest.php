<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use Illuminate\Http\UploadedFile;
use Tests\TenantTestCase;

class CatalogTransferTest extends TenantTestCase
{
    private function signIn(bool $permitted = true): TeamMember
    {
        $roles = $this->seedStandardRoles();
        $role = $permitted ? $roles['owner'] : Role::factory()->create();
        $member = TeamMember::factory()->create(['role_id' => $role->id]);

        $this->actingAs($member, 'tenant');

        return $member;
    }

    private function download(string $uri): string
    {
        $response = $this->get($uri)->assertOk();

        return $response->streamedContent() ?: $response->getContent();
    }

    public function test_the_export_neutralises_names_a_spreadsheet_would_execute(): void
    {
        $this->signIn();

        $category = MenuCategory::create(['name' => '=HYPERLINK("http://evil.test")', 'position' => 0]);
        MenuItem::factory()->create([
            'name' => '+1+1',
            'menu_category_id' => $category->id,
            'price' => '10.00',
        ]);

        $csv = $this->download(route('menu.export'));

        $this->assertStringContainsString('\'=HYPERLINK', $csv);
        $this->assertStringContainsString('\'+1+1', $csv);

        $this->assertDoesNotMatchRegularExpression('/(^|,|")[=+@]/m', $csv);
    }

    public function test_the_export_leaves_a_normal_name_alone(): void
    {
        $this->signIn();

        $category = MenuCategory::create(['name' => 'مشاوي', 'position' => 0]);
        MenuItem::factory()->create(['name' => 'كباب', 'menu_category_id' => $category->id, 'price' => '40.00']);

        $csv = $this->download(route('menu.export'));

        $this->assertStringContainsString('كباب', $csv);
        $this->assertStringNotContainsString("'كباب", $csv);
    }

    public function test_a_member_without_the_permission_cannot_export(): void
    {
        $this->signIn(permitted: false);

        $this->get(route('menu.export'))->assertForbidden();
    }

    public function test_a_csv_creates_categories_and_items(): void
    {
        $this->signIn();

        $csv = "category,name,price,active\nمشاوي,كباب,40.00,1\nمشاوي,شيش,35.00,1\n";

        $this->post(route('menu.import'), [
            'file' => UploadedFile::fake()->createWithContent('menu.csv', $csv),
        ])->assertRedirect();

        $this->assertSame(1, MenuCategory::count());
        $this->assertSame(2, MenuItem::count());
        $this->assertSame('40.00', MenuItem::where('name', 'كباب')->sole()->price);
    }

    public function test_a_negative_price_does_not_get_imported(): void
    {
        $this->signIn();

        $csv = "category,name,price,active\nمشاوي,كباب,-5.00,1\n";

        $this->post(route('menu.import'), [
            'file' => UploadedFile::fake()->createWithContent('menu.csv', $csv),
        ]);

        $this->assertSame(0, MenuItem::where('name', 'كباب')->count());
    }

    public function test_reimporting_updates_in_place(): void
    {
        $this->signIn();

        $first = "category,name,price,active\nمشاوي,كباب,40.00,1\n";
        $second = "category,name,price,active\nمشاوي,كباب,45.00,1\n";

        foreach ([$first, $second] as $csv) {
            $this->post(route('menu.import'), [
                'file' => UploadedFile::fake()->createWithContent('menu.csv', $csv),
            ]);
        }

        $this->assertSame(1, MenuItem::count());
        $this->assertSame('45.00', MenuItem::sole()->price);
    }

    public function test_a_member_without_the_permission_cannot_import(): void
    {
        $this->signIn(permitted: false);

        $this->post(route('menu.import'), [
            'file' => UploadedFile::fake()->createWithContent('menu.csv', "category,name,price,active\nx,y,1,1\n"),
        ])->assertForbidden();

        $this->assertSame(0, MenuItem::count());
    }

    public function test_an_exported_catalogue_imports_back_unchanged(): void
    {
        $this->signIn();

        $category = MenuCategory::create(['name' => 'مشروبات', 'position' => 0]);
        MenuItem::factory()->create(['name' => 'شاي', 'menu_category_id' => $category->id, 'price' => '3.00']);
        MenuItem::factory()->create(['name' => 'قهوة', 'menu_category_id' => $category->id, 'price' => '5.50']);

        $csv = $this->download(route('menu.export'));

        MenuItem::query()->delete();
        MenuCategory::query()->delete();

        $this->post(route('menu.import'), [
            'file' => UploadedFile::fake()->createWithContent('menu.csv', $csv),
        ])->assertRedirect();

        $this->assertSame(1, MenuCategory::count());
        $this->assertSame(2, MenuItem::count());
        $this->assertSame('5.50', MenuItem::where('name', 'قهوة')->sole()->price);
    }
}
