<?php

namespace App\Services\Admin;

use App\Enums\LicenseStatus;
use App\Models\Box;
use App\Models\License;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Records an incoming customer payment AND, per BACKEND_PROMPT.md section 11
 * ("Payment → license renewal"), atomically:
 *   1. Adds the amount to the receiving Box.box_balance (money coming IN —
 *      paired convention: withdrawals/expenses SUBTRACT, see
 *      WithdrawalService/ExpenseService).
 *   2. Extends the license's `expired_at` by a fixed billing cycle.
 *   3. Flips `status` to `active` if it was `trial`/`expired` (a `suspended`
 *      license is a deliberate admin decision and is NOT auto-reactivated
 *      by a payment alone — an admin must explicitly call
 *      LicenseController::activate for that).
 *   4. Adjusts `customer_balance`.
 *
 * Billing-cycle convention: `packages.billing_cycle_days` does not exist in
 * the schema (see BACKEND_PROMPT.md section 3's `packages` table) — rather
 * than silently add an unplanned migration for it, a single fixed constant
 * mirroring TenantProvisioner::TRIAL_DAYS's approach is used here. Documented
 * in this task's report as a deliberate simplification for a future pass.
 *
 * `customer_balance` sign convention (also documented in the task report):
 * treated as a running CREDIT ledger — positive means the customer has paid
 * ahead / has credit with the platform. Every payment INCREMENTS it. This
 * was chosen over "amount owed" (which a payment would decrement) because
 * (a) it's what the prompt describes as the primary behavior ("increment
 * customer_balance by the payment amount") and (b) the schema's own column
 * comment lists "مدفوعة مسبقًا" (prepaid) as one of the two things this
 * balance tracks.
 */
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
            // lockForUpdate() on both rows prevents lost updates if two
            // payments for the same box/license land concurrently.
            $box = Box::query()->lockForUpdate()->findOrFail($data['boxId']);
            $box->increment('box_balance', $data['amount']);

            $payment = Payment::create([
                'license_id' => $data['licenseId'],
                'amount' => $data['amount'],
                'box_id' => $data['boxId'],
            ]);

            $license = License::query()->lockForUpdate()->findOrFail($data['licenseId']);

            // Renew from whichever is later: the current expiry (if still in
            // the future — a customer paying ahead of time stacks the new
            // cycle on top) or now (if the license had already lapsed).
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
