<?php

namespace App\Http\Controllers\Web;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use App\Exceptions\InvalidLicenseException;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Transfer;
use App\Notifications\Tenant\OrderStatusChangedNotification;
use App\Services\Tenant\AuditLogger;
use App\Services\TenantConnectionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/**
 * Public, login-free page the bank account owner opens from the WhatsApp link
 * to confirm a transfer arrived - or report that it did not.
 *
 * There is no session and no X-License-Code here, so the tenant database is
 * selected from the license code in the URL (TenantConnectionResolver) and the
 * order is found by its unguessable token. Possession of the link IS the
 * authorization, exactly as for a signed email link; the token is the only
 * credential, the license code merely picks the database.
 */
class OrderConfirmationController extends Controller
{
    public function show(string $license, string $token): View
    {
        $transfer = $this->resolve($license, $token);

        return view('order.confirm', [
            'transfer' => $transfer,
            'license' => $license,
            'token' => $token,
            'lang' => request('lang') === 'en' ? 'en' : 'ar',
        ]);
    }

    public function act(Request $request, string $license, string $token): RedirectResponse
    {
        $transfer = $this->resolve($license, $token);

        $action = $request->input('action');

        if (! in_array($action, ['confirm', 'reject'], true)) {
            abort(422);
        }

        // Idempotent: a second submit after a decision (double tap, or
        // back-then-resubmit) changes nothing and just falls through to the
        // decided page - which is what "already confirmed" means to the owner.
        if (! $transfer->confirmationDecided()) {
            $transfer->update([
                'confirmed_at' => now(),
                'confirmation_action' => $action === 'confirm' ? 'confirmed' : 'rejected',
                'status' => $action === 'confirm' ? TransferStatus::Completed : TransferStatus::Failed,
            ]);

            // Actor resolves to "System" here - there is no logged-in user on
            // this public path (see AuditLogger::record).
            AuditLogger::record(AuditAction::Updated, AuditEntityType::Order, $transfer->id, AuditCategory::Transfers);

            $this->notifyCreator($transfer);
        }

        // PRG: land back on the GET page, which now renders the decided state,
        // keeping the language the owner was reading in.
        return redirect()->route('order.confirm', array_filter([
            'license' => $license,
            'token' => $token,
            'lang' => $request->input('lang') === 'en' ? 'en' : null,
        ]));
    }

    /**
     * Points the `tenant` connection at the license's database, then finds the
     * confirmable order by token. A bad license or an unknown/cash token is an
     * indistinguishable 404 - the page never reveals which of the two failed.
     */
    private function resolve(string $license, string $token): Transfer
    {
        try {
            TenantConnectionResolver::resolve($license);
        } catch (InvalidLicenseException) {
            abort(404);
        }

        return Transfer::query()
            ->where('confirmation_token', $token)
            ->where('payment_method', PaymentMethod::Bank)
            ->with(['lineItems', 'bank', 'customerProfile'])
            ->firstOrFail();
    }

    /**
     * Notifies only the team member who raised the order (not the whole team),
     * reusing the existing status-change notification: confirm mapped the order
     * to Completed, reject to Failed, which is exactly what it announces.
     */
    private function notifyCreator(Transfer $transfer): void
    {
        $creator = $transfer->creator; // null on API/sync-created orders

        if ($creator) {
            Notification::send($creator, new OrderStatusChangedNotification($transfer));
        }
    }
}
