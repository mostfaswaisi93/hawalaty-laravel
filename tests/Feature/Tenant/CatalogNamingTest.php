<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\TenantSetting;
use App\Services\Tenant\CatalogIcons;
use App\Services\Tenant\CatalogNaming;
use Tests\TenantTestCase;

/**
 * The catalogue is not necessarily a food menu.
 *
 * The app shipped hard-coded as a restaurant, but the same software runs a fruit
 * shop or a fuel station. These pin that each tenant owns its own wording, that
 * the restaurant defaults still apply to anyone who never changes them, and that
 * a per-item icon can only ever be a whitelisted glyph.
 */
class CatalogNamingTest extends TenantTestCase
{
    private function naming(): CatalogNaming
    {
        // Fresh instance: the service memoises per request.
        return new CatalogNaming;
    }

    private function owner(): TeamMember
    {
        return TeamMember::factory()->create(['role_id' => $this->seedStandardRoles()['owner']->id]);
    }

    /**
     * The defaults go through __(), so an untouched tenant sees the app's own
     * wording in whatever language they are viewing - "قائمة الطعام" in Arabic,
     * "Menu" in English. Compared against the translator rather than a literal
     * for exactly that reason.
     */
    public function test_an_untouched_tenant_keeps_the_shipped_wording_in_the_active_locale(): void
    {
        $naming = $this->naming();

        $this->assertSame(__('قائمة الطعام'), $naming->catalog());
        $this->assertSame(__('فئة'), $naming->category());
        $this->assertSame(__('صنف'), $naming->item());
    }

    public function test_the_default_follows_the_locale(): void
    {
        app()->setLocale('ar');
        $this->assertSame('قائمة الطعام', (new CatalogNaming)->catalog());

        app()->setLocale('en');
        $this->assertSame('Menu', (new CatalogNaming)->catalog());
    }

    public function test_a_tenant_can_name_the_catalogue_in_its_own_terms(): void
    {
        $this->naming()->put([
            CatalogNaming::CATALOG => 'أصناف الفاكهة',
            CatalogNaming::CATEGORY => 'نوع',
            CatalogNaming::ITEM => 'منتج',
        ]);

        $naming = $this->naming();
        $this->assertSame('أصناف الفاكهة', $naming->catalog());
        $this->assertSame('نوع', $naming->category());
        $this->assertSame('منتج', $naming->item());
    }

    public function test_a_blank_label_falls_back_to_the_default_rather_than_showing_empty(): void
    {
        $this->naming()->put([CatalogNaming::CATALOG => 'محطة الوقود']);
        $this->assertSame('محطة الوقود', $this->naming()->catalog());

        $this->naming()->put([CatalogNaming::CATALOG => '   ']);
        $this->assertSame(__('قائمة الطعام'), $this->naming()->catalog());
    }

    public function test_labels_are_trimmed(): void
    {
        $this->naming()->put([CatalogNaming::CATALOG => '  أنواع المحروقات  ']);

        $this->assertSame('أنواع المحروقات', $this->naming()->catalog());
    }

    /** Only registered keys are writable - a stray key must not become a setting. */
    public function test_an_unknown_key_is_ignored(): void
    {
        $this->naming()->put(['something_else' => 'nope']);

        $this->assertSame(0, TenantSetting::where('key', 'something_else')->count());
    }

    public function test_the_sidebar_uses_the_tenants_own_wording(): void
    {
        $owner = $this->owner();
        $this->naming()->put([CatalogNaming::CATALOG => 'أصناف الفاكهة']);

        $this->actingAs($owner, 'tenant')->get('/dashboard')
            ->assertOk()
            ->assertSee('أصناف الفاكهة');
    }

    public function test_the_naming_form_saves_over_http(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')
            ->put('/constants/naming', [
                'catalogLabel' => 'أنواع المحروقات',
                'categoryLabel' => 'صنف وقود',
                'itemLabel' => 'منتج',
            ])
            ->assertRedirect(route('constants.index'));

        $this->assertSame('أنواع المحروقات', $this->naming()->catalog());
    }

    public function test_an_over_long_label_is_rejected(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')
            ->put('/constants/naming', ['catalogLabel' => str_repeat('ا', 41)])
            ->assertSessionHasErrors('catalogLabel');
    }

    // --- icons ---

    public function test_an_item_can_carry_a_whitelisted_icon(): void
    {
        $category = MenuCategory::create(['name' => 'فواكه', 'icon' => 'nutrition', 'position' => 0]);
        $item = MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => 'تفاح',
            'icon' => 'nutrition',
            'price' => '5.00',
            'active' => true,
        ]);

        $this->assertSame('nutrition', $item->fresh()->icon);
        $this->assertSame('nutrition', $category->fresh()->icon);
    }

    /**
     * The value is rendered as a ligature inside a span, so free text would be a
     * way to put content on the page - and an unknown ligature draws as its own
     * literal name, which looks broken.
     */
    public function test_an_unknown_icon_is_rejected_by_validation(): void
    {
        $owner = $this->owner();
        $category = MenuCategory::create(['name' => 'فواكه', 'position' => 0]);

        $this->actingAs($owner, 'tenant')
            ->post('/menu/items', [
                'name' => 'تفاح',
                'menuCategoryId' => $category->id,
                'price' => '5.00',
                'icon' => '<script>alert(1)</script>',
            ])
            ->assertSessionHasErrors('icon');

        $this->assertSame(0, MenuItem::count());
    }

    public function test_the_icon_resolver_falls_back_entry_then_category_then_neutral(): void
    {
        $icons = new CatalogIcons;

        $this->assertSame('nutrition', $icons->resolve('nutrition', 'restaurant'));
        $this->assertSame('restaurant', $icons->resolve(null, 'restaurant'));
        $this->assertSame('restaurant', $icons->resolve('made-up', 'restaurant'));
        $this->assertSame('category', $icons->resolve(null, null));
        $this->assertSame('category', $icons->resolve('made-up', 'also-made-up'));
    }

    public function test_every_offered_icon_is_accepted_by_the_validator(): void
    {
        $icons = new CatalogIcons;

        $this->assertNotEmpty($icons->all());

        foreach ($icons->all() as $icon) {
            $this->assertTrue($icons->isValid($icon), "Offered icon `{$icon}` is not considered valid.");
        }
    }
}
