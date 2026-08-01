<?php

namespace App\Services\Admin;

use App\Models\Box;
use App\Models\Expense;
use App\Models\Partner;
use App\Models\Payment;

class FinanceSummaryService
{
    public function summarize(): array
    {
        $totalPayments = (float) Payment::query()->sum('amount');
        $totalExpenses = (float) Expense::query()->sum('amount');

        $boxes = Box::query()->get();
        $totalBoxBalance = (float) $boxes->sum('box_balance');

        $partners = Partner::query()->get();

        return [
            'totalPayments' => $totalPayments,
            'totalExpenses' => $totalExpenses,
            'boxes' => $boxes->map(fn (Box $box) => [
                'id' => (string) $box->id,
                'name' => $box->box_name,
                'balance' => (float) $box->box_balance,
            ])->all(),
            'partners' => $partners->map(fn (Partner $partner) => [
                'id' => (string) $partner->id,
                'name' => $partner->name,
                'percentage' => (float) $partner->percentage,
                'computedShare' => round($totalBoxBalance * (float) $partner->percentage / 100, 2),
            ])->all(),
        ];
    }
}
