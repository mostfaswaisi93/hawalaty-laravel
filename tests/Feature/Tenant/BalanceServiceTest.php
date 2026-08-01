<?php

namespace Tests\Feature\Tenant;

use App\Enums\ChangeMethod;
use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use App\Models\Tenant\Account;
use App\Models\Tenant\Expense;
use App\Models\Tenant\TeamMember;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\BalanceService;
use App\Services\Tenant\SalaryService;
use Tests\TenantTestCase;

/**
 * الموازنة المالية.
 *
 * The rules that decide what a figure MEANS are what these pin: only completed
 * orders count as money in, salaries are a subtotal inside the outflow rather
 * than an addition to it, and change is deliberately left out of the net.
 * Getting any of those wrong produces a statement that looks right and is not.
 */
class BalanceServiceTest extends TenantTestCase
{
    private function service(): BalanceService
    {
        return app(BalanceService::class);
    }

    private function order(array $attributes = []): Transfer
    {
        return Transfer::factory()->create($attributes + [
            'status' => TransferStatus::Completed,
            'payment_method' => PaymentMethod::Cash,
            'amount' => '100.00',
            'created_at' => '2026-07-10 12:00:00',
        ]);
    }

    public function test_net_is_completed_income_minus_expenses(): void
    {
        $bank = Account::factory()->create();

        $this->order(['amount' => '300.00']);
        $this->order(['amount' => '200.00']);
        Expense::create(['description' => 'كهرباء', 'amount' => '120.00', 'account_id' => $bank->id, 'date' => '2026-07-12']);

        $s = $this->service()->summary('2026-07-01', '2026-07-31');

        $this->assertSame(500.0, $s['totalIn']);
        $this->assertSame(120.0, $s['totalOut']);
        $this->assertSame(380.0, $s['net']);
        $this->assertSame(2, $s['orderCount']);
    }

    /** A pending order is not collected money; counting it overstates the position. */
    public function test_pending_orders_are_reported_but_excluded_from_income(): void
    {
        $this->order(['amount' => '400.00']);
        $this->order(['amount' => '150.00', 'status' => TransferStatus::Pending]);

        $s = $this->service()->summary('2026-07-01', '2026-07-31');

        $this->assertSame(400.0, $s['totalIn'], 'A pending order leaked into income.');
        $this->assertSame(150.0, $s['pending']);
    }

    public function test_failed_orders_are_excluded_entirely(): void
    {
        $this->order(['amount' => '400.00']);
        $this->order(['amount' => '999.00', 'status' => TransferStatus::Failed]);

        $s = $this->service()->summary('2026-07-01', '2026-07-31');

        $this->assertSame(400.0, $s['totalIn']);
        $this->assertSame(0.0, $s['pending']);
    }

    /**
     * Paying a salary writes an expense, so payroll is already inside totalOut.
     * Reporting it as a subtotal must not double-count it.
     */
    public function test_salaries_are_a_subtotal_of_the_outflow_not_an_addition(): void
    {
        $bank = Account::factory()->create();
        $member = TeamMember::factory()->create(['role_id' => $this->seedStandardRoles()['staff']->id]);

        Expense::create(['description' => 'إيجار', 'amount' => '300.00', 'account_id' => $bank->id, 'date' => '2026-07-05']);
        app(SalaryService::class)->pay($member, ['amount' => '700.00', 'accountId' => $bank->id, 'paidFor' => '2026-07-06']);

        $s = $this->service()->summary('2026-07-01', '2026-07-31');

        $this->assertSame(1000.0, $s['totalOut'], 'Salary was not counted inside the outflow.');
        $this->assertSame(700.0, $s['salaries']);
        $this->assertSame(300.0, $s['otherExpenses']);
        $this->assertSame($s['totalOut'], $s['salaries'] + $s['otherExpenses']);
    }

    /**
     * Whether change is an outflow is a business question, so it is surfaced but
     * never silently netted.
     */
    public function test_change_is_reported_but_left_out_of_the_net(): void
    {
        $this->order(['amount' => '500.00', 'has_change' => true, 'change_amount' => '80.00']);

        $s = $this->service()->summary('2026-07-01', '2026-07-31');

        $this->assertSame(80.0, $s['changeGiven']);
        $this->assertSame(500.0, $s['net'], 'Change was netted off without being asked for.');
    }

    public function test_a_bank_order_credits_its_bank_and_cash_credits_the_drawer(): void
    {
        $bank = Account::factory()->create(['holder_name' => 'بنك فلسطين']);

        $this->order(['amount' => '250.00', 'payment_method' => PaymentMethod::Bank, 'account_id' => $bank->id]);
        $this->order(['amount' => '90.00']); // cash
        Expense::create(['description' => 'صيانة', 'amount' => '40.00', 'account_id' => $bank->id, 'date' => '2026-07-15']);

        $rows = collect($this->service()->byAccount('2026-07-01', '2026-07-31'))->keyBy('name');

        $this->assertSame(250.0, $rows[$bank->label()]['in']);
        $this->assertSame(40.0, $rows[$bank->label()]['out']);
        $this->assertSame(210.0, $rows[$bank->label()]['net']);

        $this->assertSame(90.0, $rows[__('نقدي')]['in']);
        $this->assertTrue($rows[__('نقدي')]['isCash']);
    }

    /** Twenty idle wallets would bury the two that actually moved. */
    public function test_accounts_with_no_movement_are_omitted(): void
    {
        $idle = Account::factory()->create(['holder_name' => 'ساكن']);
        $active = Account::factory()->create(['holder_name' => 'متحرك']);
        $this->order(['amount' => '100.00', 'payment_method' => PaymentMethod::Bank, 'account_id' => $active->id]);

        $names = collect($this->service()->byAccount('2026-07-01', '2026-07-31'))->pluck('name');

        $this->assertTrue($names->contains($active->label()));
        $this->assertFalse($names->contains($idle->label()));
    }

    /** The per-account rows must reconcile with the headline totals. */
    public function test_account_rows_sum_to_the_headline_figures(): void
    {
        $a = Account::factory()->create();
        $b = Account::factory()->create();

        $this->order(['amount' => '120.00', 'payment_method' => PaymentMethod::Bank, 'account_id' => $a->id]);
        $this->order(['amount' => '80.00', 'payment_method' => PaymentMethod::Bank, 'account_id' => $b->id]);
        $this->order(['amount' => '55.00']); // cash
        Expense::create(['description' => 'x', 'amount' => '30.00', 'account_id' => $a->id, 'date' => '2026-07-11']);

        $s = $this->service()->summary('2026-07-01', '2026-07-31');
        $rows = $this->service()->byAccount('2026-07-01', '2026-07-31');

        $this->assertSame($s['totalIn'], round(array_sum(array_column($rows, 'in')), 2));
        $this->assertSame($s['totalOut'], round(array_sum(array_column($rows, 'out')), 2));
    }

    /**
     * Change paid out of a DIFFERENT account than the one that took the payment
     * is a real movement between two accounts, so BOTH legs must be booked.
     */
    public function test_change_paid_from_another_account_moves_money_between_them(): void
    {
        $wallet = Account::factory()->create(['holder_name' => 'محفظة']);

        // 100 order paid in cash; 30 of change sent back from the wallet. The
        // customer therefore handed over 130.
        $this->order([
            'amount' => '100.00',
            'payment_method' => PaymentMethod::Cash,
            'has_change' => true,
            'change_method' => ChangeMethod::Bank,
            'change_account_id' => $wallet->id,
            'change_amount' => '30.00',
        ]);

        $rows = collect($this->service()->byAccount('2026-07-01', '2026-07-31'))->keyBy('name');

        $this->assertSame(130.0, $rows[__('نقدي')]['in'], 'The drawer took the whole tender, not just the order total.');
        $this->assertSame(0.0, $rows[__('نقدي')]['out']);

        $this->assertSame(30.0, $rows[$wallet->label()]['out'], 'The wallet paid the change out and must show it.');
        $this->assertSame(-30.0, $rows[$wallet->label()]['net']);
    }

    /** Change returned to the account that paid nets to zero - do not inflate it. */
    public function test_change_within_one_account_is_not_booked(): void
    {
        $this->order([
            'amount' => '100.00',
            'payment_method' => PaymentMethod::Cash,
            'has_change' => true,
            'change_method' => ChangeMethod::Cash,
            'change_amount' => '30.00',
        ]);

        $rows = collect($this->service()->byAccount('2026-07-01', '2026-07-31'))->keyBy('name');

        $this->assertSame(100.0, $rows[__('نقدي')]['in']);
        $this->assertSame(0.0, $rows[__('نقدي')]['out']);
    }

    /**
     * The invariant that survives cross-account change, where `Σ in == totalIn`
     * no longer can: the accounts still net to the headline position.
     */
    public function test_account_rows_always_net_to_the_headline_position(): void
    {
        $bank = Account::factory()->create();
        $wallet = Account::factory()->create();

        $this->order(['amount' => '200.00', 'payment_method' => PaymentMethod::Bank, 'account_id' => $bank->id]);
        $this->order([
            'amount' => '100.00',
            'payment_method' => PaymentMethod::Cash,
            'has_change' => true,
            'change_method' => ChangeMethod::Bank,
            'change_account_id' => $wallet->id,
            'change_amount' => '30.00',
        ]);
        Expense::create(['description' => 'x', 'amount' => '45.00', 'account_id' => $bank->id, 'date' => '2026-07-11']);

        $s = $this->service()->summary('2026-07-01', '2026-07-31');
        $rows = $this->service()->byAccount('2026-07-01', '2026-07-31');

        $this->assertSame(
            round($s['totalIn'] - $s['totalOut'], 2),
            round(array_sum(array_column($rows, 'net')), 2),
        );
    }

    /**
     * `expenses.date` is a cast date stored as "Y-m-d H:i:s", so an inclusive
     * upper bound drops the final day - the same trap as the settlement window.
     */
    public function test_movement_on_the_last_day_of_the_period_is_included(): void
    {
        $bank = Account::factory()->create();

        $this->order(['amount' => '100.00', 'created_at' => '2026-07-31 23:59:59']);
        Expense::create(['description' => 'آخر يوم', 'amount' => '25.00', 'account_id' => $bank->id, 'date' => '2026-07-31']);

        $s = $this->service()->summary('2026-07-01', '2026-07-31');

        $this->assertSame(100.0, $s['totalIn'], 'An order on the last day was dropped.');
        $this->assertSame(25.0, $s['totalOut'], 'An expense on the last day was dropped.');
    }

    public function test_movement_after_the_period_is_excluded(): void
    {
        $bank = Account::factory()->create();

        $this->order(['amount' => '100.00', 'created_at' => '2026-08-01 00:00:00']);
        Expense::create(['description' => 'أغسطس', 'amount' => '25.00', 'account_id' => $bank->id, 'date' => '2026-08-01']);

        $s = $this->service()->summary('2026-07-01', '2026-07-31');

        $this->assertSame(0.0, $s['totalIn']);
        $this->assertSame(0.0, $s['totalOut']);
    }

    public function test_the_page_renders_for_a_signed_in_member(): void
    {
        $owner = TeamMember::factory()->create(['role_id' => $this->seedStandardRoles()['owner']->id]);

        $this->actingAs($owner, 'tenant')->get('/balance')
            ->assertOk()
            ->assertViewHas('summary')
            ->assertViewHas('accounts')
            ->assertSee(__('الموازنة المالية'));
    }

    public function test_an_end_date_before_the_start_is_rejected(): void
    {
        $owner = TeamMember::factory()->create(['role_id' => $this->seedStandardRoles()['owner']->id]);

        $this->actingAs($owner, 'tenant')
            ->get('/balance?from=2026-07-31&to=2026-07-01')
            ->assertSessionHasErrors('to');
    }
}
