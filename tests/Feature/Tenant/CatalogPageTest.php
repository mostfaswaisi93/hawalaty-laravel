<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\AccountType;
use App\Models\Tenant\BankName;
use App\Models\Tenant\Currency;
use App\Models\Tenant\TeamMember;
use Tests\TenantTestCase;

/**
 * Phase 2a - the reference catalogs screen (currencies, bank names, wallet
 * names, account types, wallet types, cash boxes), driven by one
 * registry-backed controller.
 *
 * Covers the guards (who may reach it, who may write to it), the shared
 * CRUD, the currency-only symbol field, name uniqueness, the active toggle as
 * a partial update, and that deletion soft-deletes.
 */
class CatalogPageTest extends TenantTestCase
{
    private function owner(): TeamMember
    {
        return TeamMember::factory()->create(['role_id' => $this->seedStandardRoles()['owner']->id]);
    }

    private function staff(): TeamMember
    {
        return TeamMember::factory()->create(['role_id' => $this->seedStandardRoles()['staff']->id]);
    }

    public function test_the_page_lists_the_six_catalogs(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')->get('/catalogs')
            ->assertOk()
            ->assertViewHas('catalogs')
            ->assertSee(__('العملات'))
            ->assertSee(__('البنوك'))
            ->assertSee(__('المحافظ'))
            ->assertSee(__('أنواع الحسابات'))
            ->assertSee(__('أنواع المحافظ'))
            ->assertSee(__('الصناديق النقدية'));
    }

    /** BanksManage governs the banks/wallets vocabulary, so it governs this too. */
    public function test_a_member_without_banks_manage_cannot_reach_the_page(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'tenant')->get('/catalogs')->assertRedirect(route('dashboard'));
    }

    public function test_a_member_without_banks_manage_cannot_create_an_item(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'tenant')
            ->post('/catalogs/banks', ['name' => 'بنك مهرَّب', 'active' => 1])
            ->assertForbidden();

        $this->assertSame(0, BankName::count());
    }

    public function test_a_bank_name_can_be_created(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')
            ->post('/catalogs/banks', ['name' => 'بنك فلسطين', 'active' => 1])
            ->assertRedirectContains(route('catalogs.index'));

        $this->assertSame(1, BankName::where('name', 'بنك فلسطين')->count());
    }

    public function test_a_currency_requires_a_symbol(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')
            ->post('/catalogs/currencies', ['name' => 'شيكل', 'active' => 1])
            ->assertSessionHasErrors('symbol');

        $this->assertSame(0, Currency::count());
    }

    public function test_a_currency_is_stored_with_its_symbol(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')
            ->post('/catalogs/currencies', ['name' => 'شيكل', 'symbol' => '₪', 'active' => 1])
            ->assertRedirectContains(route('catalogs.index'));

        $currency = Currency::firstOrFail();
        $this->assertSame('شيكل', $currency->name);
        $this->assertSame('₪', $currency->symbol);
        $this->assertTrue($currency->active);
    }

    /** A non-currency catalog never persists a symbol, even if one is posted. */
    public function test_a_symbol_is_ignored_for_non_currency_catalogs(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')
            ->post('/catalogs/account-types', ['name' => 'توفير', 'symbol' => 'X', 'active' => 1])
            ->assertRedirectContains(route('catalogs.index'));

        $type = AccountType::firstOrFail();
        $this->assertSame('توفير', $type->name);
        $this->assertArrayNotHasKey('symbol', $type->getAttributes());
    }

    public function test_a_duplicate_name_within_a_catalog_is_rejected(): void
    {
        $owner = $this->owner();
        BankName::factory()->create(['name' => 'بنك فلسطين']);

        $this->actingAs($owner, 'tenant')
            ->post('/catalogs/banks', ['name' => 'بنك فلسطين', 'active' => 1])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, BankName::where('name', 'بنك فلسطين')->count());
    }

    /** The same name is fine in a different catalog - uniqueness is per-table. */
    public function test_the_same_name_may_exist_in_two_different_catalogs(): void
    {
        $owner = $this->owner();
        BankName::factory()->create(['name' => 'مشترك']);

        $this->actingAs($owner, 'tenant')
            ->post('/catalogs/wallets', ['name' => 'مشترك', 'active' => 1])
            ->assertRedirectContains(route('catalogs.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_an_item_can_be_renamed(): void
    {
        $owner = $this->owner();
        $bank = BankName::factory()->create(['name' => 'اسم قديم']);

        $this->actingAs($owner, 'tenant')
            ->put("/catalogs/banks/{$bank->id}", ['name' => 'اسم جديد', 'active' => 1])
            ->assertRedirectContains(route('catalogs.index'));

        $this->assertSame('اسم جديد', $bank->fresh()->name);
    }

    /** The switch posts only `active`; that must be a valid partial update. */
    public function test_the_active_toggle_is_a_valid_partial_update(): void
    {
        $owner = $this->owner();
        $bank = BankName::factory()->create(['active' => true]);

        $this->actingAs($owner, 'tenant')
            ->put("/catalogs/banks/{$bank->id}", ['active' => 0])
            ->assertRedirectContains(route('catalogs.index'));

        $this->assertFalse($bank->fresh()->active);
    }

    public function test_deletion_soft_deletes(): void
    {
        $owner = $this->owner();
        $bank = BankName::factory()->create();

        $this->actingAs($owner, 'tenant')
            ->delete("/catalogs/banks/{$bank->id}")
            ->assertRedirectContains(route('catalogs.index'));

        $this->assertNull(BankName::find($bank->id));
        $this->assertNotNull(BankName::withTrashed()->find($bank->id));
    }

    /** The {catalog} route constraint rejects anything outside the six keys. */
    public function test_an_unknown_catalog_key_is_not_routable(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'tenant')
            ->post('/catalogs/not-a-catalog', ['name' => 'X'])
            ->assertNotFound();
    }
}
