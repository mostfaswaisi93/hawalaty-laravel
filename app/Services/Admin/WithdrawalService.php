<?php

namespace App\Services\Admin;

use App\Exceptions\InsufficientBoxBalanceException;
use App\Models\Box;
use App\Models\Withdrawal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
            $box = Box::query()->lockForUpdate()->findOrFail($data['boxId']);

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
