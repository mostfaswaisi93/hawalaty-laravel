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

        if (! $transfer->confirmationDecided()) {
            $transfer->update([
                'confirmed_at' => now(),
                'confirmation_action' => $action === 'confirm' ? 'confirmed' : 'rejected',
                'status' => $action === 'confirm' ? TransferStatus::Completed : TransferStatus::Failed,
            ]);

            AuditLogger::record(AuditAction::Updated, AuditEntityType::Order, $transfer->id, AuditCategory::Transfers);

            $this->notifyCreator($transfer);
        }

        return redirect()->route('order.confirm', array_filter([
            'license' => $license,
            'token' => $token,
            'lang' => $request->input('lang') === 'en' ? 'en' : null,
        ]));
    }

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
            ->with(['lineItems', 'customerProfile', 'account.bankName', 'account.walletName', 'account.cashBox', 'account.currency'])
            ->firstOrFail();
    }

    private function notifyCreator(Transfer $transfer): void
    {
        $creator = $transfer->creator;

        if ($creator) {
            Notification::send($creator, new OrderStatusChangedNotification($transfer));
        }
    }
}
