<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Enums\SalaryPeriod;
use App\Models\Tenant\Expense;
use App\Models\Tenant\SalaryPayment;
use App\Models\Tenant\TeamMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Staff payroll.
 *
 * Paying a salary is not a separate kind of bookkeeping - it is money leaving a
 * bank or wallet, exactly like any other expense. So `pay()` writes BOTH rows in
 * one transaction: an `expenses` row (which is what makes it show up in
 * المصروفات and in the reports, and which records the account it came from) and
 * a `salary_payments` row carrying the part an expense cannot express - who was
 * paid, and for which period.
 *
 * Doing it any other way would mean either salaries invisible to the expense
 * totals, or a second "salary" concept the reports would have to know about.
 */
class SalaryService
{
    /**
     * Records a salary payment and the expense that carries the money movement.
     *
     * @param  array{amount: numeric-string|float, accountId: string, paidFor?: ?string, period?: ?string, notes?: ?string}  $data
     */
    public function pay(TeamMember $member, array $data): SalaryPayment
    {
        return DB::transaction(function () use ($member, $data) {
            $paidFor = isset($data['paidFor']) && $data['paidFor']
                ? Carbon::parse($data['paidFor'])
                : Carbon::today();

            // Falls back to the member's configured rate, but the SUBMITTED
            // amount always wins - a bonus, a deduction or a part payment must
            // be recordable without editing the rate first.
            $period = $data['period'] ?? $member->salary_period?->value ?? SalaryPeriod::Monthly->value;

            $expense = Expense::create([
                'description' => $this->describe($member, $period, $paidFor),
                'amount' => $data['amount'],
                'account_id' => $data['accountId'],
                'date' => $paidFor->toDateString(),
            ]);

            $payment = SalaryPayment::create([
                'team_member_id' => $member->id,
                'expense_id' => $expense->id,
                'amount' => $data['amount'],
                'period' => $period,
                'paid_for' => $paidFor->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            AuditLogger::record(
                AuditAction::Created,
                AuditEntityType::Expense,
                $expense->description,
                AuditCategory::Expenses,
            );

            return $payment->load(['teamMember', 'expense.account']);
        });
    }

    /**
     * The payroll ledger, newest first.
     */
    public function history(?string $memberId = null, int $perPage = 20): LengthAwarePaginator
    {
        return SalaryPayment::query()
            ->with(['teamMember', 'expense.account'])
            ->when($memberId, fn ($q) => $q->where('team_member_id', $memberId))
            ->latest('paid_for')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Total paid out in salaries within a window - lets the reports separate
     * payroll from ordinary running costs without a second source of truth.
     */
    public function totalPaid(string $from, string $to): float
    {
        return (float) SalaryPayment::query()
            ->where('paid_for', '>=', $from)
            ->where('paid_for', '<', Carbon::parse($to)->addDay()->toDateString())
            ->sum('amount');
    }

    /**
     * The expense description. Written once, here, so every salary expense reads
     * the same in المصروفات and can be recognised at a glance.
     */
    private function describe(TeamMember $member, string $period, Carbon $paidFor): string
    {
        $label = SalaryPeriod::from($period) === SalaryPeriod::Daily
            ? $paidFor->translatedFormat('j F Y')
            : $paidFor->translatedFormat('F Y');

        return __('راتب :name - :period', ['name' => $member->name, 'period' => $label]);
    }
}
