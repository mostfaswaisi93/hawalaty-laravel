<?php

namespace App\Services\Admin;

use App\Enums\LicenseStatus;
use App\Models\Box;
use App\Models\License;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    protected const BILLING_CYCLE_DAYS = 30;

    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['box', 'license'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $box = Box::query()->lockForUpdate()->findOrFail($data['boxId']);
            $box->increment('box_balance', $data['amount']);

            $payment = Payment::create([
                'license_id' => $data['licenseId'],
                'amount' => $data['amount'],
                'box_id' => $data['boxId'],
            ]);

            $license = License::query()->lockForUpdate()->findOrFail($data['licenseId']);

            $base = ($license->expired_at && $license->expired_at->isFuture())
                ? $license->expired_at
                : now();

            $license->expired_at = $base->copy()->addDays(self::BILLING_CYCLE_DAYS);

            if (in_array($license->status, [LicenseStatus::Trial, LicenseStatus::Expired], true)) {
                $license->status = LicenseStatus::Active;
            }

            $license->customer_balance = (float) $license->customer_balance + (float) $data['amount'];
            $license->save();

            return $payment->load(['box', 'license']);
        });
    }
}
