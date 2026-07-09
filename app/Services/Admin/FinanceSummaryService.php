<?php

namespace App\Services\Admin;

use App\Models\Box;
use App\Models\Expense;
use App\Models\Partner;
use App\Models\Payment;

/**
 * Turns the platform's own accounting tables into an actual dashboard — see
 * BACKEND_PROMPT.md section 11, "ملخّص مالي للمنصّة". Deliberately not a
 * Controller method: this is real aggregation business logic, not a
 * passthrough Resource mapping.
 */
class FinanceSummaryService
{
    /**
     * @return array{
     *     totalPayments: float,
     *     totalExpenses: float,
     *     boxes: array<int, array{id: string, name: string, balance: float}>,
     *     partners: array<int, array{id: string, name: string, percentage: float, computedShare: float}>,
     * }
     */
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
