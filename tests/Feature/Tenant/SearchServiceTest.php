<?php

namespace Tests\Feature\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\Account;
use App\Models\Tenant\Customer;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Role;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\SearchService;
use Tests\TenantTestCase;

/**
 * Global search, shared by the header pill and the mobile API.
 *
 * Two things here are load-bearing rather than cosmetic:
 *   - a PHONE has to find the customer's ORDERS, not just the customer row;
 *   - soft-deleted rows must not come back, which an unwrapped OR would let
 *     happen because the soft-delete scope only binds to the last branch.
 */
class SearchServiceTest extends TenantTestCase
{
    private SearchService $service;

    private TeamMember $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SearchService::class);
        $roles = $this->seedStandardRoles();
        $this->owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
    }

    public function test_a_query_shorter_than_two_characters_returns_nothing(): void
    {
        Customer::factory()->create(['name' => 'محمود', 'phone' => '0599123321']);

        $results = $this->service->search('م', $this->owner);

        $this->assertCount(0, $results['customers']);
        $this->assertFalse($this->service->isSearchable('م'));
    }

    public function test_a_customer_is_found_by_name_or_phone(): void
    {
        Customer::factory()->create(['name' => 'سارة', 'phone' => '0598765432']);
        Customer::factory()->create(['name' => 'وليد', 'phone' => '0591111111']);

        $this->assertCount(1, $this->service->search('سارة', $this->owner)['customers']);
        $this->assertSame(
            'سارة',
            $this->service->search('8765432', $this->owner)['customers']->first()['name'],
        );
    }

    /**
     * The gap that prompted this: a phone lives on the customer row, so orders
     * used to be invisible to the single most common search in a transfer app.
     */
    public function test_an_order_is_found_by_its_customers_phone(): void
    {
        $customer = Customer::factory()->create(['name' => 'ليلى', 'phone' => '0597654321']);
        Transfer::factory()->create(['customer' => 'ليلى', 'customer_id' => $customer->id]);

        $orders = $this->service->search('7654321', $this->owner)['orders'];

        $this->assertCount(1, $orders);
        $this->assertSame('0597654321', $orders->first()['phone']);
    }

    public function test_an_order_is_found_by_sender_phone_and_item_name(): void
    {
        Transfer::factory()->create(['customer' => 'خالد', 'sender_phone' => '0569998887']);
        Transfer::factory()->create(['customer' => 'ريم', 'item_name' => 'شاورما دجاج']);

        $this->assertCount(1, $this->service->search('9998887', $this->owner)['orders']);
        $this->assertCount(1, $this->service->search('شاورما', $this->owner)['orders']);
    }

    /**
     * With the OR left unwrapped, the soft-delete scope binds to the last branch
     * only and a deleted order matching an earlier branch comes back.
     */
    public function test_soft_deleted_orders_and_banks_stay_hidden(): void
    {
        $transfer = Transfer::factory()->create(['customer' => 'مطلوب حذفه']);
        $transfer->delete();

        $bank = Account::factory()->create(['holder_name' => 'بنك محذوف']);
        $bank->delete();

        $this->assertCount(0, $this->service->search('مطلوب', $this->owner)['orders']);
        $this->assertCount(0, $this->service->search('محذوف', $this->owner)['banks']);
    }

    /**
     * An operator looks for the INSTITUTION as often as the holder ("بنك
     * فلسطين", not "سامي"), so the account is reachable by either.
     */
    public function test_an_account_is_found_by_its_institution_name(): void
    {
        $bankName = \App\Models\Tenant\BankName::factory()->create(['name' => 'بنك فلسطين']);
        Account::factory()->create(['holder_name' => 'سامي نبهان', 'bank_name_id' => $bankName->id]);

        $this->assertCount(1, $this->service->search('فلسطين', $this->owner)['banks']);
        $this->assertCount(1, $this->service->search('سامي', $this->owner)['banks']);
    }

    /** A catalogue hit carries the icon the catalogue page would draw. */
    public function test_a_catalogue_entry_falls_back_to_its_categorys_icon(): void
    {
        $category = MenuCategory::create(['name' => 'وقود', 'position' => 0, 'icon' => 'local_gas_station']);
        MenuItem::factory()->create(['name' => 'سولار', 'menu_category_id' => $category->id, 'icon' => null]);

        $hit = $this->service->search('سولار', $this->owner)['menuItems']->first();

        $this->assertSame('local_gas_station', $hit['icon']);
        $this->assertSame('وقود', $hit['categoryName']);
    }

    public function test_banks_and_catalogue_are_hidden_without_the_permission(): void
    {
        Account::factory()->create(['holder_name' => 'بنك فلسطين']);
        $category = MenuCategory::create(['name' => 'مشاوي', 'position' => 0]);
        MenuItem::factory()->create(['name' => 'بنك برجر', 'menu_category_id' => $category->id]);

        $powerless = TeamMember::factory()->create(['role_id' => Role::factory()->create()->id]);

        $results = $this->service->search('بنك', $powerless);

        $this->assertCount(0, $results['banks']);
        $this->assertCount(0, $results['menuItems']);
        // ...while the groups every member may see still work.
        $this->assertTrue($powerless->hasPermission(PermissionKey::BanksManage) === false);
    }

    /**
     * `%` is a LIKE wildcard. Unescaped, "%%" would match every row - a way to
     * dump the tables through a box that is supposed to need two real characters.
     */
    public function test_like_wildcards_in_the_query_are_escaped(): void
    {
        Customer::factory()->create(['name' => 'سارة', 'phone' => '0598765432']);
        Customer::factory()->create(['name' => 'وليد', 'phone' => '0591111111']);

        $this->assertCount(0, $this->service->search('%%', $this->owner)['customers']);
        $this->assertCount(0, $this->service->search('__', $this->owner)['customers']);
    }
}
