<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\BusinessLogo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    public function show(string $transfer): Response
    {
        $order = Transfer::with(['lineItems', 'account.bankName', 'account.walletName', 'account.cashBox', 'account.currency', 'changeAccount'])->findOrFail($transfer);

        $license = app()->bound('currentLicense') ? app('currentLicense') : null;

        $pdf = Pdf::loadView('receipts.transfer', [
            'order' => $order,
            'businessName' => $license?->customer_name ?? __('حوالاتي'),
            'symbol' => $order->account?->currency?->symbol ?? '₪',

            'logo' => app(BusinessLogo::class)->dataUri(),
        ])->setPaper([0, 0, 226.77, 566.93]);

        return $pdf->download("receipt-{$order->id}.pdf");
    }
}
