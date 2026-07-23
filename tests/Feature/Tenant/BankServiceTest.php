<?php

namespace Tests\Feature\Tenant;

use App\Enums\BankType;
use App\Models\Tenant\Bank;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\BankService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Tests\TenantTestCase;

/**
 * Regression coverage for BankService: the active-defaulting fix, the
 * delete() history guard (expenses / paying transfers / change transfers),
 * the DB-level unique constraint on banks.code, and list() search.
 */
class BankServiceTest extends TenantTestCase
{
    private function service(): BankService
    {
        return app(BankService::class);
    }

    public function test_create_defaults_active_to_true_when_omitted(): void
    {
        $bank = $this->service()->create([
            'name' => 'بنك تجريبي',
            'type' => BankType::Bank,
            'code' => 'BNK100',
        ]);

        $this->assertTrue($bank->active);
    }

    public function test_create_respects_an_explicit_active_value(): void
    {
        $bank = $this->service()->create([
            'name' => 'بنك معطل',
            'type' => BankType::Bank,
            'code' => 'BNK101',
            'active' => false,
        ]);

        $this->assertFalse($bank->active);
    }

    public function test_delete_throws_when_bank_has_expense_history(): void
    {
        $bank = Bank::factory()->create();
        Expense::factory()->create(['bank_id' => $bank->id]);

        $this->expectException(ValidationException::class);

        $this->service()->delete($bank);
    }

    public function test_delete_throws_when_bank_has_paying_transfer_history(): void
    {
        $bank = Bank::factory()->create();
        Transfer::factory()->create(['bank_id' => $bank->id]);

        $this->expectException(ValidationException::class);

        $this->service()->delete($bank);
    }

    public function test_delete_throws_when_bank_has_change_transfer_history(): void
    {
        $bank = Bank::factory()->create();
        Transfer::factory()->create(['change_bank_id' => $bank->id]);

        $this->expectException(ValidationException::class);

        $this->service()->delete($bank);
    }

    public function test_delete_succeeds_when_bank_has_no_history(): void
    {
        $bank = Bank::factory()->create();

        $this->service()->delete($bank);

        $this->assertNull(Bank::find($bank->id));
    }

    public function test_creating_two_banks_with_the_same_code_raises_a_query_exception(): void
    {
        Bank::create(['name' => 'بنك أ', 'type' => BankType::Bank, 'code' => 'DUP001', 'active' => true]);

        $this->expectException(QueryException::class);

        Bank::create(['name' => 'بنك ب', 'type' => BankType::Bank, 'code' => 'DUP001', 'active' => true]);
    }

    public function test_list_filters_by_name_substring(): void
    {
        $first = Bank::factory()->create(['name' => 'بنك الأمل']);
        $second = Bank::factory()->create(['name' => 'بنك النور']);
        Bank::factory()->create(['name' => 'محفظة كاش']);

        $results = $this->service()->list('بنك');

        $this->assertCount(2, $results);
        $this->assertSame([$first->id, $second->id], $results->pluck('id')->sort()->values()->all());
    }
}
