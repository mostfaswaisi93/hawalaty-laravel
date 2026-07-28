<?php

namespace App\Services\Admin;

use App\Exceptions\InsufficientBoxBalanceException;
use App\Models\Box;
use App\Models\Withdrawal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * A partner's withdrawal from a platform box - see BACKEND_PROMPT.md
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

            // Checked HERE, not only in the FormRequest. Two withdrawals
            // submitted at the same moment can both pass a request-level check
            // against the same starting balance; only inside this lock is the
            // balance the real one. Without it the box simply went negative -
            // there is no CHECK constraint on the column to catch it either.
            if (bccomp((string) $box->box_balance, (string) $data['amount'], 2) < 0) {
                throw new InsufficientBoxBalanceException(
                    $box->box_name,
                    (float) $box->box_balance,
                    (float) $data['amount'],
                );
            }

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
