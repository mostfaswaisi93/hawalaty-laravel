<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\Expense;
use App\Services\Tenant\ExpenseService;
use Tests\TenantTestCase;

/**
 * Regression coverage for ExpenseService: standard CRUD, the SoftDeletes
 * behavior of delete(), the Expense::bank() withTrashed() relation surviving
 * a soft-deleted Bank, and list() search + pagination.
 */
class ExpenseServiceTest extends TenantTestCase
{
    private function service(): ExpenseService
    {
        return app(ExpenseService::class);
    }

    public function test_create_persists_an_expense_and_loads_its_bank(): void
    {
        $bank = Account::factory()->create(['holder_name' => 'بنك الرياض']);

        $expense = $this->service()->create([
            'description' => 'فاتورة كهرباء',
            'amount' => '150.00',
            'accountId' => $bank->id,
            'date' => '2026-07-01',
        ]);

        $this->assertSame('فاتورة كهرباء', $expense->description);
        $this->assertSame('150.00', $expense->amount);
        $this->assertSame($bank->id, $expense->account_id);
        $this->assertSame('بنك الرياض', $expense->account->holder_name);
    }

    public function test_update_changes_only_the_given_fields(): void
    {
        $bank = Account::factory()->create();
        $expense = Expense::factory()->create([
            'account_id' => $bank->id,
            'description' => 'مصروف قديم',
            'amount' => '20.00',
        ]);

        $updated = $this->service()->update($expense, ['description' => 'مصروف جديد']);

        $this->assertSame('مصروف جديد', $updated->description);
        $this->assertSame('20.00', $updated->amount);
        $this->assertSame($bank->id, $updated->account_id);
    }

    public function test_delete_soft_deletes_the_expense(): void
    {
        $bank = Account::factory()->create();
        $expense = Expense::factory()->create(['account_id' => $bank->id]);

        $this->service()->delete($expense);

        $this->assertNull(Expense::find($expense->id));

        $trashed = Expense::withTrashed()->find($expense->id);
        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);
    }

    public function test_bank_relation_still_resolves_after_the_bank_is_soft_deleted(): void
    {
        $bank = Account::factory()->create(['holder_name' => 'بنك محذوف']);
        $expense = Expense::factory()->create(['account_id' => $bank->id]);

        // Bypasses BankService::delete()'s history guard on purpose - this
        // test targets the withTrashed() relation, not the guard.
        $bank->delete();

        $reloaded = Expense::find($expense->id);

        $this->assertNotNull($reloaded->account);
        $this->assertSame('بنك محذوف', $reloaded->account->holder_name);
    }

    public function test_list_filters_by_description_substring_and_paginates(): void
    {
        $bank = Account::factory()->create();

        Expense::factory()->count(25)->create(['account_id' => $bank->id, 'description' => 'مصروف عام']);
        Expense::factory()->create(['account_id' => $bank->id, 'description' => 'إيجار المحل']);

        $all = $this->service()->list();
        $this->assertCount(20, $all->items());
        $this->assertSame(26, $all->total());
        $this->assertTrue($all->hasMorePages());

        $filtered = $this->service()->list('إيجار');
        $this->assertSame(1, $filtered->total());
        $this->assertSame('إيجار المحل', $filtered->items()[0]->description);
    }
}
