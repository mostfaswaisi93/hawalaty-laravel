<?php

namespace App\Services\Tenant;

use App\Enums\ChangeMethod;
use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use App\Models\Tenant\Account;
use App\Models\Tenant\Expense;
use App\Models\Tenant\SalaryPayment;
use App\Models\Tenant\Transfer;
use Illuminate\Support\Carbon;

class BalanceService
{
    public const CASH = '__cash__';

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

    public function byAccount(string $from, string $to): array
    {
        [$start, $end] = $this->window($from, $to);

        $inByAccount = Transfer::query()
            ->where('status', TransferStatus::Completed->value)
            ->where('payment_method', PaymentMethod::Bank->value)
            ->whereNotNull('account_id')
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->groupBy('account_id')
            ->selectRaw('account_id, SUM(amount) as total')
            ->pluck('total', 'account_id');

        $outByAccount = Expense::query()
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<', $end->toDateString())
            ->groupBy('account_id')
            ->selectRaw('account_id, SUM(amount) as total')
            ->pluck('total', 'account_id');

        $cashIn = (float) Transfer::query()
            ->where('status', TransferStatus::Completed->value)
            ->where('payment_method', PaymentMethod::Cash->value)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->sum('amount');

        $cashOut = 0.0;

        foreach ($this->crossAccountChange($start, $end) as $leg) {
            if ($leg['fromAccountId'] === null) {
                $cashOut += $leg['amount'];
            } else {
                $outByAccount[$leg['fromAccountId']] = (float) ($outByAccount[$leg['fromAccountId']] ?? 0) + $leg['amount'];
            }

            if ($leg['toAccountId'] === null) {
                $cashIn += $leg['amount'];
            } else {
                $inByAccount[$leg['toAccountId']] = (float) ($inByAccount[$leg['toAccountId']] ?? 0) + $leg['amount'];
            }
        }

        $rows = [];

        $accounts = Account::withTrashed()
            ->with(['bankName', 'walletName'])
            ->orderBy('holder_name')
            ->get();

        foreach ($accounts as $account) {
            $in = round((float) ($inByAccount[$account->id] ?? 0), 2);
            $out = round((float) ($outByAccount[$account->id] ?? 0), 2);

            if ($in === 0.0 && $out === 0.0) {
                continue;
            }

            $rows[] = [
                'id' => (string) $account->id,
                'name' => $account->label(),
                'isCash' => false,
                'in' => $in,
                'out' => $out,
                'net' => round($in - $out, 2),
            ];
        }

        if ($cashIn > 0 || $cashOut > 0) {
            array_unshift($rows, [
                'id' => self::CASH,
                'name' => __('نقدي'),
                'isCash' => true,
                'in' => round($cashIn, 2),

                'out' => round($cashOut, 2),
                'net' => round($cashIn - $cashOut, 2),
            ]);
        }

        return $rows;
    }

    private function crossAccountChange(Carbon $start, Carbon $end): array
    {
        $orders = Transfer::query()
            ->where('status', TransferStatus::Completed->value)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->whereNotNull('change_amount')
            ->where('change_amount', '>', 0)
            ->get(['payment_method', 'account_id', 'change_method', 'change_account_id', 'change_amount']);

        $legs = [];

        foreach ($orders as $order) {
            $to = $order->payment_method === PaymentMethod::Bank ? $order->account_id : null;
            $from = $order->change_method === ChangeMethod::Bank ? $order->change_account_id : null;

            if ($from === $to) {
                continue;
            }

            $legs[] = [
                'amount' => (float) $order->change_amount,
                'fromAccountId' => $from,
                'toAccountId' => $to,
            ];
        }

        return $legs;
    }

    private function window(string $from, string $to): array
    {
        return [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->startOfDay()->addDay(),
        ];
    }
}
