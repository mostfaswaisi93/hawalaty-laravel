<?php

namespace Tests\Feature\Tenant;

use App\Enums\SalaryPeriod;
use App\Models\Tenant\Bank;
use App\Models\Tenant\Expense;
use App\Models\Tenant\SalaryPayment;
use App\Models\Tenant\TeamMember;
use App\Services\Tenant\SalaryService;
use Illuminate\Support\Carbon;
use Tests\TenantTestCase;

/**
 * Payroll.
 *
 * The property that matters most: paying a salary IS an expense. If the two ever
 * come apart, salaries either vanish from the expense totals or turn into a
 * second ledger nobody reconciles - so most of this pins that pairing, the
 * account the money left from, and the fact that snapshots survive a later
 * change to the member's rate.
 */
class SalaryServiceTest extends TenantTestCase
{
    private function service(): SalaryService
    {
        return app(SalaryService::class);
    }

    private function member(array $attributes = []): TeamMember
    {
        return TeamMember::factory()->create($attributes + [
            'role_id' => $this->seedStandardRoles()['staff']->id,
        ]);
    }

    public function test_paying_a_salary_creates_a_matching_expense_on_the_chosen_account(): void
    {
        $bank = Bank::factory()->create(['name' => 'بنك فلسطين']);
        $member = $this->member(['name' => 'يوسف النجّار']);

        $payment = $this->service()->pay($member, [
            'amount' => '1500.00',
            'bankId' => $bank->id,
            'paidFor' => '2026-07-01',
        ]);

        $this->assertSame('1500.00', $payment->amount);
        $this->assertSame($member->id, $payment->team_member_id);

        // The money movement itself.
        $expense = $payment->expense;
        $this->assertNotNull($expense, 'A salary payment without an expense is invisible to the books.');
        $this->assertSame('1500.00', $expense->amount);
        $this->assertSame($bank->id, $expense->bank_id, 'The salary did not record which account it left.');
        $this->assertSame('2026-07-01', $expense->date->toDateString());
        $this->assertStringContainsString('يوسف النجّار', $expense->description);
    }

    /** It has to be countable as an ordinary expense, with no special-casing. */
    public function test_the_salary_shows_up_in_the_expense_totals(): void
    {
        $bank = Bank::factory()->create();
        $member = $this->member();

        Expense::create(['description' => 'كهرباء', 'amount' => '200.00', 'bank_id' => $bank->id, 'date' => '2026-07-05']);
        $this->service()->pay($member, ['amount' => '800.00', 'bankId' => $bank->id, 'paidFor' => '2026-07-05']);

        $this->assertSame(2, Expense::count());
        $this->assertSame('1000.00', number_format((float) Expense::sum('amount'), 2, '.', ''));
    }

    public function test_the_whole_write_is_atomic(): void
    {
        $member = $this->member();

        // A bank id that does not exist trips the foreign key.
        try {
            $this->service()->pay($member, [
                'amount' => '500.00',
                'bankId' => '01000000000000000000000000',
                'paidFor' => '2026-07-01',
            ]);
            $this->fail('Expected the invalid bank reference to fail.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(0, Expense::count(), 'An expense survived a failed salary payment.');
        $this->assertSame(0, SalaryPayment::count());
    }

    /**
     * The rate is only a DEFAULT. A bonus or a part payment must be recordable
     * without editing it first, and the amount actually paid is what is stored.
     */
    public function test_the_submitted_amount_wins_over_the_members_rate(): void
    {
        $bank = Bank::factory()->create();
        $member = $this->member(['salary_amount' => '1000.00', 'salary_period' => SalaryPeriod::Monthly]);

        $payment = $this->service()->pay($member, [
            'amount' => '1250.00',
            'bankId' => $bank->id,
            'paidFor' => '2026-07-01',
        ]);

        $this->assertSame('1250.00', $payment->amount);
        $this->assertSame('1000.00', $member->fresh()->salary_amount, 'Paying a bonus rewrote the agreed rate.');
    }

    public function test_raising_the_rate_later_does_not_rewrite_a_paid_salary(): void
    {
        $bank = Bank::factory()->create();
        $member = $this->member(['salary_amount' => '1000.00', 'salary_period' => SalaryPeriod::Monthly]);

        $payment = $this->service()->pay($member, ['amount' => '1000.00', 'bankId' => $bank->id, 'paidFor' => '2026-06-01']);

        $member->update(['salary_amount' => '1800.00']);

        $this->assertSame('1000.00', $payment->fresh()->amount);
        $this->assertSame('1000.00', $payment->fresh()->expense->amount);
    }

    public function test_the_period_falls_back_to_the_members_own_cycle(): void
    {
        $bank = Bank::factory()->create();
        $member = $this->member(['salary_amount' => '90.00', 'salary_period' => SalaryPeriod::Daily]);

        $payment = $this->service()->pay($member, ['amount' => '90.00', 'bankId' => $bank->id, 'paidFor' => '2026-07-10']);

        $this->assertSame(SalaryPeriod::Daily, $payment->period);
        // A daily payment names the day; a monthly one names the month.
        $this->assertStringContainsString('10', $payment->expense->description);
    }

    public function test_total_paid_covers_the_window_inclusively_at_both_ends(): void
    {
        $bank = Bank::factory()->create();
        $member = $this->member();

        $this->service()->pay($member, ['amount' => '100.00', 'bankId' => $bank->id, 'paidFor' => '2026-07-01']);
        $this->service()->pay($member, ['amount' => '200.00', 'bankId' => $bank->id, 'paidFor' => '2026-07-31']);
        $this->service()->pay($member, ['amount' => '999.00', 'bankId' => $bank->id, 'paidFor' => '2026-08-01']);

        $this->assertSame(300.0, $this->service()->totalPaid('2026-07-01', '2026-07-31'));
    }

    public function test_history_is_newest_first_and_carries_the_paying_account(): void
    {
        $bank = Bank::factory()->create(['name' => 'جوال باي']);
        $member = $this->member();

        $this->service()->pay($member, ['amount' => '100.00', 'bankId' => $bank->id, 'paidFor' => '2026-06-01']);
        $this->service()->pay($member, ['amount' => '200.00', 'bankId' => $bank->id, 'paidFor' => '2026-07-01']);

        $page = $this->service()->history();
        $first = $page->getCollection()->first();

        $this->assertSame('200.00', $first->amount);
        $this->assertSame('جوال باي', $first->expense->bank->name);
    }

    /** Deleting the expense must not leave a payment claiming someone was paid. */
    public function test_force_deleting_the_expense_removes_its_salary_payment(): void
    {
        $bank = Bank::factory()->create();
        $member = $this->member();

        $payment = $this->service()->pay($member, ['amount' => '300.00', 'bankId' => $bank->id, 'paidFor' => '2026-07-01']);

        $payment->expense->forceDelete();

        $this->assertSame(0, SalaryPayment::count());
    }

    /** Soft-deleting it keeps the record, which is what an audit trail needs. */
    public function test_soft_deleting_the_expense_keeps_the_payment_readable(): void
    {
        $bank = Bank::factory()->create(['name' => 'بنك القدس']);
        $member = $this->member();

        $payment = $this->service()->pay($member, ['amount' => '300.00', 'bankId' => $bank->id, 'paidFor' => '2026-07-01']);
        $payment->expense->delete();

        $fresh = $payment->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('بنك القدس', $fresh->expense->bank->name);
    }

    public function test_paid_for_defaults_to_today_when_omitted(): void
    {
        $bank = Bank::factory()->create();
        $member = $this->member();

        $payment = $this->service()->pay($member, ['amount' => '50.00', 'bankId' => $bank->id]);

        $this->assertSame(Carbon::today()->toDateString(), $payment->paid_for->toDateString());
    }

    public function test_the_team_page_renders_the_payroll_column(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
        Bank::factory()->create();

        $this->actingAs($owner, 'tenant')->get('/team')
            ->assertOk()
            ->assertViewHas('banks')
            ->assertViewHas('salaryHistory')
            ->assertSee(__('الراتب'));
    }

    public function test_a_salary_can_be_paid_over_http(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
        $staff = TeamMember::factory()->create(['role_id' => $roles['staff']->id]);
        $bank = Bank::factory()->create();

        $this->actingAs($owner, 'tenant')
            ->post("/team/{$staff->id}/salary/pay", [
                'amount' => '1200.00',
                'bankId' => $bank->id,
                'paidFor' => '2026-07-01',
                'period' => 'monthly',
            ])
            ->assertRedirect(route('team.index'));

        $this->assertSame(1, SalaryPayment::count());
        $this->assertSame(1, Expense::count());
        $this->assertSame($bank->id, Expense::first()->bank_id);
    }

    /** A future-dated salary would silently skew every report it lands in. */
    public function test_a_future_paid_for_date_is_rejected(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);
        $bank = Bank::factory()->create();

        $this->actingAs($owner, 'tenant')
            ->post("/team/{$owner->id}/salary/pay", [
                'amount' => '100.00',
                'bankId' => $bank->id,
                'paidFor' => Carbon::today()->addMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('paidFor');

        $this->assertSame(0, SalaryPayment::count());
    }

    /** A rate is unusable with only one half set, so each half requires the other. */
    public function test_setting_a_rate_requires_both_amount_and_cycle(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create(['role_id' => $roles['owner']->id]);

        $this->actingAs($owner, 'tenant')
            ->put("/team/{$owner->id}/salary", ['salaryAmount' => '900'])
            ->assertSessionHasErrors('salaryPeriod');

        $this->actingAs($owner, 'tenant')
            ->put("/team/{$owner->id}/salary", ['salaryAmount' => '900', 'salaryPeriod' => 'monthly'])
            ->assertRedirect(route('team.index'));

        $this->assertSame('900.00', $owner->fresh()->salary_amount);
        $this->assertSame(SalaryPeriod::Monthly, $owner->fresh()->salary_period);
    }

    public function test_clearing_the_rate_is_allowed(): void
    {
        $roles = $this->seedStandardRoles();
        $owner = TeamMember::factory()->create([
            'role_id' => $roles['owner']->id,
            'salary_amount' => '900.00',
            'salary_period' => SalaryPeriod::Monthly,
        ]);

        $this->actingAs($owner, 'tenant')
            ->put("/team/{$owner->id}/salary", ['salaryAmount' => null, 'salaryPeriod' => null])
            ->assertRedirect(route('team.index'));

        $this->assertNull($owner->fresh()->salary_amount);
        $this->assertFalse($owner->fresh()->hasSalary());
    }
}
