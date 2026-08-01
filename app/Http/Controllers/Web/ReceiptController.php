<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\BusinessLogo;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function show(string $transfer): View
    {
        $order = Transfer::with([
            'lineItems', 'customerProfile',
            'account.bankName', 'account.walletName', 'account.cashBox', 'account.currency',
            'changeAccount',
        ])->findOrFail($transfer);

        $license = app()->bound('currentLicense') ? app('currentLicense') : null;

        return view('receipts.transfer-print', [
            'order' => $order,

            'address' => $license?->address,
            'phone' => $license?->phone,

            'printedBy' => auth('tenant')->user()?->name ?? __('حوالاتي'),
            'businessName' => $license?->customer_name ?? __('حوالاتي'),

            'symbol' => $order->account?->currency?->symbol ?? '₪',

            'logo' => app(BusinessLogo::class)->dataUri(),
        ]);
    }
}
