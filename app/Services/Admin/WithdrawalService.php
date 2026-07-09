<?php

namespace App\Services\Admin;

use App\Models\Box;
use App\Models\Withdrawal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * A partner's withdrawal from a platform box — see BACKEND_PROMPT.md
 * section 11's "get the balance math right" requirement. Money leaving a
 * box always SUBTRACTS from `Box.box_balance` (paired convention: payments
 * ADD, see PaymentService; expenses SUBTRACT, see ExpenseService).
 */
class WithdrawalService
{
    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return Withdrawal::query()
            ->with(['box', 'partner'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Withdrawal
    {
        return DB::transaction(function () use ($data) {
            // lockForUpdate() prevents a lost-update race if two withdrawals
            // against the same box are processed concurrently.
            $box = Box::query()->lockForUpdate()->findOrFail($data['boxId']);
            $box->decrement('box_balance', $data['amount']);

            $withdrawal = Withdrawal::create([
                'box_id' => $data['boxId'],
                'amount' => $data['amount'],
                'partner_id' => $data['partnerId'],
            ]);

            return $withdrawal->load(['box', 'partner']);
        });
    }
}
