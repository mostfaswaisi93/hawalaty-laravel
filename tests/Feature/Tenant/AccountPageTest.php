<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\AccountType;
use App\Models\Tenant\BankName;
use App\Models\Tenant\Currency;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\WalletName;
use App\Models\Tenant\WalletType;
use App\Services\Tenant\AccountService;
use Tests\TenantTestCase;

/**
 * Phase 2b - the accounts screens and their guards: who may reach them,
 * bank/wallet creation, the currency-must-be-supported rule, the manual ledger
 * entry, and the two deletion guards (an account with history, and a catalog
 * row still used by an account).
 */
class AccountPageTest extends TenantTestCase
{
    private function owner(): TeamMember
    {
        return TeamMember::factory()->create(['role_id' => $this->seedStandardRoles()['owner']->id]);
    }

    private function staff(): TeamMember
    {
        return TeamMember::factory()->create(['role_id' => $this->seedStandardRoles()['staff']->id]);
    }

    private function supportedBank(Currency $currency): BankName
    {
        $bank = BankName::factory()->create();
        $bank->currencies()->attach($currency->id);

        return $bank;
    }

    public function test_the_index_is_gated_on_banks_manage(): void
    {
        // seedStandardRoles() inserts the 7 UNIQUE permissions, so it must be
        // called once per test - seed here and build both members from it.
        $roles = $this->seedStandardRoles();
        $staff = TeamMember::factory()->create(['role_id' => $roles['staff']->id]);
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $this->actingAs($staff, 'tenant')->get('/accounts')->assertRedirect(route('dashboard'));
        $this->actingAs($owner, 'tenant')->get('/accounts')->assertOk()->assertViewHas('accounts');
    }

    public function test_the_create_page_renders_for_a_manager(): void
    {
        $this->actingAs($this->owner(), 'tenant')->get('/accounts/create')
            ->assertOk()
            ->assertViewHas('banks')
            ->assertViewHas('currencies');
    }

    public function test_a_bank_account_is_created_when_the_currency_is_supported(): void
    {
        $currency = Currency::factory()->create();
        $bank = $this->supportedBank($currency);
        $type = AccountType::factory()->create();

        $this->actingAs($this->owner(), 'tenant')->post('/accounts', [
            'kind' => 'bank',
            'bank_name_id' => $bank->id,
            'account_type_id' => $type->id,
            'currency_id' => $currency->id,
            'holder_name' => 'أحمد محمد',
            'iban' => 'PS00TEST',
        ])->assertRedirect();

        $account = Account::where('holder_name', 'أحمد محمد')->firstOrFail();
        $this->assertSame('bank', $account->kind->value);
        $this->assertSame($bank->id, $account->bank_name_id);
        $this->assertNull($account->wallet_name_id);
    }

    public function test_a_currency_not_supported_by_the_bank_is_rejected(): void
    {
        $currency = Currency::factory()->create();
        $bank = BankName::factory()->create(); // supports no currencies
        $type = AccountType::factory()->create();

        $this->actingAs($this->owner(), 'tenant')->post('/accounts', [
            'kind' => 'bank',
            'bank_name_id' => $bank->id,
            'account_type_id' => $type->id,
            'currency_id' => $currency->id,
            'holder_name' => 'خالد',
        ])->assertSessionHasErrors('currency_id');

        $this->assertSame(0, Account::count());
    }

    public function test_a_wallet_account_is_created(): void
    {
        $currency = Currency::factory()->create();
        $wallet = WalletName::factory()->create();
        $wallet->currencies()->attach($currency->id);
        $type = WalletType::factory()->create();

        $this->actingAs($this->owner(), 'tenant')->post('/accounts', [
            'kind' => 'wallet',
            'wallet_name_id' => $wallet->id,
            'wallet_type_id' => $type->id,
            'wallet_phone' => '0595817016',
            'currency_id' => $currency->id,
            'holder_name' => 'ليان',
        ])->assertRedirect();

        $account = Account::where('holder_name', 'ليان')->firstOrFail();
        $this->assertSame('wallet', $account->kind->value);
        $this->assertSame('0595817016', $account->wallet_phone);
        $this->assertNull($account->bank_name_id);
    }

    public function test_a_bank_account_requires_the_bank_fields(): void
    {
        $currency = Currency::factory()->create();

        $this->actingAs($this->owner(), 'tenant')->post('/accounts', [
            'kind' => 'bank',
            'currency_id' => $currency->id,
            'holder_name' => 'ناقص',
        ])->assertSessionHasErrors(['bank_name_id', 'account_type_id']);
    }

    public function test_the_whatsapp_number_is_normalised_to_e164(): void
    {
        $currency = Currency::factory()->create();
        $bank = $this->supportedBank($currency);
        $type = AccountType::factory()->create();

        $this->actingAs($this->owner(), 'tenant')->post('/accounts', [
            'kind' => 'bank',
            'bank_name_id' => $bank->id,
            'account_type_id' => $type->id,
            'currency_id' => $currency->id,
            'holder_name' => 'واتساب',
            'whatsapp_phone' => '0595817016',
        ])->assertRedirect();

        $this->assertSame('+972595817016', Account::where('holder_name', 'واتساب')->firstOrFail()->whatsapp_phone);
    }

    public function test_a_staff_member_cannot_create_an_account(): void
    {
        $currency = Currency::factory()->create();
        $bank = $this->supportedBank($currency);
        $type = AccountType::factory()->create();

        $this->actingAs($this->staff(), 'tenant')->post('/accounts', [
            'kind' => 'bank',
            'bank_name_id' => $bank->id,
            'account_type_id' => $type->id,
            'currency_id' => $currency->id,
            'holder_name' => 'مهرَّب',
        ])->assertForbidden();

        $this->assertSame(0, Account::count());
    }

    public function test_the_show_page_lists_the_statement(): void
    {
        $account = Account::factory()->create();
        app(AccountService::class)->postEntry($account, 100, 0, 'إيداع تجريبي');

        $this->actingAs($this->owner(), 'tenant')->get(route('accounts.show', $account->id))
            ->assertOk()
            ->assertViewHas('entries')
            ->assertSee('إيداع تجريبي')
            ->assertSee(__('كشف الحركات'));
    }

    public function test_a_manual_entry_moves_the_balance(): void
    {
        $account = Account::factory()->create();

        $this->actingAs($this->owner(), 'tenant')
            ->post(route('accounts.entries.store', $account->id), [
                'direction' => 'debit',
                'amount' => 150,
                'description' => 'إيداع',
            ])->assertRedirect(route('accounts.show', $account->id));

        $this->assertSame(150.0, (float) $account->fresh()->balance);
        $this->assertSame(1, $account->ledgerEntries()->count());
    }

    public function test_an_account_with_history_is_not_deleted_via_http(): void
    {
        $account = Account::factory()->create();
        app(AccountService::class)->postEntry($account, 50, 0);

        $this->actingAs($this->owner(), 'tenant')->delete(route('accounts.destroy', $account->id))
            ->assertRedirect(route('accounts.index'));

        $this->assertNotNull(Account::find($account->id), 'An account with ledger history was deleted.');
    }

    public function test_an_empty_account_is_deleted_via_http(): void
    {
        $account = Account::factory()->create();

        $this->actingAs($this->owner(), 'tenant')->delete(route('accounts.destroy', $account->id))
            ->assertRedirect(route('accounts.index'));

        $this->assertNull(Account::find($account->id));
    }

    /** A catalog row still referenced by an account must not be deletable. */
    public function test_a_bank_name_in_use_by_an_account_cannot_be_deleted(): void
    {
        $account = Account::factory()->create();
        $bankNameId = $account->bank_name_id;

        $this->actingAs($this->owner(), 'tenant')->delete("/catalogs/banks/{$bankNameId}")
            ->assertRedirectContains(route('catalogs.index'));

        $this->assertNotNull(BankName::find($bankNameId), 'A bank name still used by an account was deleted.');
    }
}
