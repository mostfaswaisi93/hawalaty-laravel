<?php

namespace App\Services\Admin;

use App\Models\Box;
use App\Models\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * The platform's OWN operating expenses (hosting, domain, SSL...) - see
 * App\Models\Expense's doc comment. Money leaving a box always SUBTRACTS
 * from `Box.box_balance` (paired convention: payments ADD, see
 * PaymentService; withdrawals SUBTRACT, see WithdrawalService).
 */
class ExpenseService
{
    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return Expense::query()
            ->with('box')
            ->latest('date')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            $box = Box::query()->lockForUpdate()->findOrFail($data['boxId']);
            $box->decrement('box_balance', $data['amount']);

            $expense = Expense::create([
                'box_id' => $data['boxId'],
                'description' => $data['description'],
                'amount' => $data['amount'],
                'date' => $data['date'],
            ]);

            return $expense->load('box');
        });
    }
}
