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

class SalaryService
{
    public function pay(TeamMember $member, array $data): SalaryPayment
    {
        return DB::transaction(function () use ($member, $data) {
            $paidFor = isset($data['paidFor']) && $data['paidFor']
                ? Carbon::parse($data['paidFor'])
                : Carbon::today();

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

    public function totalPaid(string $from, string $to): float
    {
        return (float) SalaryPayment::query()
            ->where('paid_for', '>=', $from)
            ->where('paid_for', '<', Carbon::parse($to)->addDay()->toDateString())
            ->sum('amount');
    }

    private function describe(TeamMember $member, string $period, Carbon $paidFor): string
    {
        $label = SalaryPeriod::from($period) === SalaryPeriod::Daily
            ? $paidFor->translatedFormat('j F Y')
            : $paidFor->translatedFormat('F Y');

        return __('راتب :name - :period', ['name' => $member->name, 'period' => $label]);
    }
}
