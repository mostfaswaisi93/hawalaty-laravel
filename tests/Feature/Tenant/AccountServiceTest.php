<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\AccountType;
use App\Models\Tenant\BankName;
use App\Models\Tenant\Currency;
use App\Models\Tenant\LedgerEntry;
use App\Services\Tenant\AccountService;
use Illuminate\Validation\ValidationException;
use Tests\TenantTestCase;

class AccountServiceTest extends TenantTestCase
{
    private function service(): AccountService
    {
        return app(AccountService::class);
    }

    public function test_a_new_account_starts_at_zero_with_no_entries(): void
    {
        $account = Account::factory()->create();

        $this->assertSame('0.00', (string) $account->balance);
        $this->assertSame(0, $account->ledgerEntries()->count());
    }

    public function test_debit_adds_and_credit_subtracts_with_correct_running_balances(): void
    {
        $account = Account::factory()->create();

        $this->service()->postEntry($account, 100, 0, 'إيداع');
        $this->service()->postEntry($account, 0, 30, 'سحب');
        $this->service()->postEntry($account, 50, 0, 'إيداع');

        $this->assertSame('120.00', (string) $account->fresh()->balance);

        $running = $account->ledgerEntries()->orderBy('id')->pluck('running_balance')
            ->map(fn ($v) => (float) $v)->all();
        $this->assertSame([100.0, 70.0, 120.0], $running);
    }

    public function test_the_cached_balance_always_equals_the_last_entrys_running_balance(): void
    {
        $account = Account::factory()->create();

        $this->service()->postEntry($account, 250.75, 0);
        $this->service()->postEntry($account, 0, 100.25);

        $last = $account->ledgerEntries()->orderByDesc('id')->first();
        $this->assertSame((float) $last->running_balance, (float) $account->fresh()->balance);
        $this->assertSame(150.5, (float) $account->fresh()->balance);
    }

    public function test_a_credit_can_take_the_balance_negative(): void
    {
        $account = Account::factory()->create();

        $this->service()->postEntry($account, 0, 40);

        $this->assertSame(-40.0, (float) $account->fresh()->balance);
    }

    public function test_create_with_a_positive_opening_balance_posts_a_debit(): void
    {
        $currency = Currency::factory()->create();
        $bank = BankName::factory()->create();
        $type = AccountType::factory()->create();

        $account = $this->service()->create([
            'kind' => 'bank',
            'bank_name_id' => $bank->id,
            'account_type_id' => $type->id,
            'currency_id' => $currency->id,
            'holder_name' => 'أحمد',
            'opening_balance' => 500,
        ]);

        $this->assertSame(500.0, (float) $account->balance);
        $entry = $account->ledgerEntries()->sole();
        $this->assertSame(500.0, (float) $entry->debit);
        $this->assertSame(0.0, (float) $entry->credit);
    }

    public function test_create_with_a_negative_opening_balance_posts_a_credit(): void
    {
        $currency = Currency::factory()->create();
        $bank = BankName::factory()->create();
        $type = AccountType::factory()->create();

        $account = $this->service()->create([
            'kind' => 'bank',
            'bank_name_id' => $bank->id,
            'account_type_id' => $type->id,
            'currency_id' => $currency->id,
            'holder_name' => 'سمير',
            'opening_balance' => -200,
        ]);

        $this->assertSame(-200.0, (float) $account->balance);
        $this->assertSame(200.0, (float) $account->ledgerEntries()->sole()->credit);
    }

    public function test_create_without_an_opening_balance_writes_no_entry(): void
    {
        $currency = Currency::factory()->create();
        $bank = BankName::factory()->create();
        $type = AccountType::factory()->create();

        $account = $this->service()->create([
            'kind' => 'bank',
            'bank_name_id' => $bank->id,
            'account_type_id' => $type->id,
            'currency_id' => $currency->id,
            'holder_name' => 'ليان',
        ]);

        $this->assertSame(0, $account->ledgerEntries()->count());
        $this->assertSame('0.00', (string) $account->balance);
    }

    public function test_an_account_with_ledger_history_cannot_be_deleted(): void
    {
        $account = Account::factory()->create();
        $this->service()->postEntry($account, 100, 0);

        $this->expectException(ValidationException::class);

        $this->service()->delete($account);
    }

    public function test_an_account_without_history_can_be_deleted(): void
    {
        $account = Account::factory()->create();

        $this->service()->delete($account);

        $this->assertNull(Account::find($account->id));
    }

    public function test_a_bank_account_edit_never_rewrites_the_wallet_side(): void
    {
        $account = Account::factory()->create(['iban' => 'PS111']);

        $this->service()->update($account, [
            'holder_name' => 'اسم جديد',
            'iban' => 'PS999',
        ]);

        $fresh = $account->fresh();
        $this->assertSame('اسم جديد', $fresh->holder_name);
        $this->assertSame('PS999', $fresh->iban);
        $this->assertNull($fresh->wallet_name_id);

        $this->assertNotNull($fresh->currency_id);
        $this->assertSame($account->currency_id, $fresh->currency_id);
    }

    public function test_ledger_entries_are_append_only_without_an_updated_at(): void
    {
        $account = Account::factory()->create();
        $this->service()->postEntry($account, 10, 0);

        $this->assertNull(LedgerEntry::UPDATED_AT);
    }
}
