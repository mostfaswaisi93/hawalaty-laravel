<?php

namespace App\Services\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use App\Models\Tenant\Bank;
use App\Models\Tenant\Expense;
use App\Models\Tenant\SalaryPayment;
use App\Models\Tenant\Transfer;
use Illuminate\Support\Carbon;

/**
 * الموازنة المالية - money in versus money out, per account, over a period.
 *
 * WHAT COUNTS
 * IN  = COMPLETED orders only. A pending order is not collected money, and
 *       counting it would overstate the position; it is reported separately as
 *       "expected" so the number is visible without polluting the balance.
 *       Failed orders are excluded entirely.
 * OUT = expenses. Salaries need no special case because paying one writes an
 *       expense (see SalaryService) - they are broken out as a subtotal purely
 *       so payroll is legible, never added on top.
 *
 * PER ACCOUNT
 * A bank-paid order credits its bank; a cash order credits the cash drawer,
 * which has no `banks` row and is therefore reported as its own synthetic line.
 * Expenses always carry a bank, so they debit it directly.
 *
 * CHANGE IS NOT NETTED. `transfers.change_amount` is money handed back on a cash
 * order, but whether it is an outflow or merely part of the tender is a business
 * question this code should not answer silently - it is surfaced as its own
 * figure, clearly labelled, and left out of the balance.
 *
 * Windows are HALF-OPEN [from, to+1day) throughout - the same reason as
 * SettlementService: `expenses.date` is a cast date stored as "Y-m-d H:i:s", so
 * an inclusive upper bound silently drops the final day.
 */
class BalanceService
{
    /** Synthetic account id for cash, which has no `banks` row. */
    public const CASH = '__cash__';

    /**
     * @return array{
     *     from: Carbon, to: Carbon,
     *     totalIn: float, totalOut: float, net: float,
     *     salaries: float, otherExpenses: float,
     *     pending: float, changeGiven: float,
     *     orderCount: int, expenseCount: int,
     * }
     */
    public function summary(string $from, string $to): array
    {
        [$start, $end] = $this->window($from, $to);

        $completed = Transfer::query()
            ->where('status', TransferStatus::Completed->value)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end);

        $totalIn = (float) (clone $completed)->sum('amount');
        $orderCount = (int) (clone $completed)->count();

        $pending = (float) Transfer::query()
            ->where('status', TransferStatus::Pending->value)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->sum('amount');

        // Informational only - deliberately absent from `net`.
        $changeGiven = (float) Transfer::query()
            ->where('status', TransferStatus::Completed->value)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->whereNotNull('change_amount')
            ->sum('change_amount');

        $expenses = Expense::query()
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<', $end->toDateString());

        $totalOut = (float) (clone $expenses)->sum('amount');
        $expenseCount = (int) (clone $expenses)->count();

        // A subtotal WITHIN totalOut, never added to it.
        $salaries = (float) SalaryPayment::query()
            ->where('paid_for', '>=', $start->toDateString())
            ->where('paid_for', '<', $end->toDateString())
            ->sum('amount');

        return [
            'from' => $start->copy(),
            'to' => $end->copy()->subDay(),
            'totalIn' => round($totalIn, 2),
            'totalOut' => round($totalOut, 2),
            'net' => round($totalIn - $totalOut, 2),
            'salaries' => round($salaries, 2),
            'otherExpenses' => round($totalOut - $salaries, 2),
            'pending' => round($pending, 2),
            'changeGiven' => round($changeGiven, 2),
            'orderCount' => $orderCount,
            'expenseCount' => $expenseCount,
        ];
    }

    /**
     * One row per account - every bank/wallet plus the cash drawer - with what
     * came in, what went out, and the difference.
     *
     * Accounts with no movement at all are dropped: a statement listing twenty
     * idle wallets buries the two that actually moved.
     *
     * @return array<int, array{id: string, name: string, isCash: bool, in: float, out: float, net: float}>
     */
    public function byAccount(string $from, string $to): array
    {
        [$start, $end] = $this->window($from, $to);

        $inByBank = Transfer::query()
            ->where('status', TransferStatus::Completed->value)
            ->where('payment_method', PaymentMethod::Bank->value)
            ->whereNotNull('bank_id')
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->groupBy('bank_id')
            ->selectRaw('bank_id, SUM(amount) as total')
            ->pluck('total', 'bank_id');

        $outByBank = Expense::query()
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<', $end->toDateString())
            ->groupBy('bank_id')
            ->selectRaw('bank_id, SUM(amount) as total')
            ->pluck('total', 'bank_id');

        $cashIn = (float) Transfer::query()
            ->where('status', TransferStatus::Completed->value)
            ->where('payment_method', PaymentMethod::Cash->value)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->sum('amount');

        $rows = [];

        // withTrashed: a retired bank must still show the money that moved
        // through it during the period, or the rows will not sum to the total.
        foreach (Bank::withTrashed()->orderBy('name')->get() as $bank) {
            $in = round((float) ($inByBank[$bank->id] ?? 0), 2);
            $out = round((float) ($outByBank[$bank->id] ?? 0), 2);

            if ($in === 0.0 && $out === 0.0) {
                continue;
            }

            $rows[] = [
                'id' => (string) $bank->id,
                'name' => $bank->name,
                'isCash' => false,
                'in' => $in,
                'out' => $out,
                'net' => round($in - $out, 2),
            ];
        }

        if ($cashIn > 0) {
            array_unshift($rows, [
                'id' => self::CASH,
                'name' => __('نقدي'),
                'isCash' => true,
                'in' => round($cashIn, 2),
                // Cash has no account row, so an expense can never be booked
                // against it - every expense names a bank.
                'out' => 0.0,
                'net' => round($cashIn, 2),
            ]);
        }

        return $rows;
    }

    /**
     * @return array{0: Carbon, 1: Carbon} [start of day, exclusive end]
     */
    private function window(string $from, string $to): array
    {
        return [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->startOfDay()->addDay(),
        ];
    }
}
