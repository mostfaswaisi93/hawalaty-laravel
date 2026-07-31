<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\BusinessLogo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * The printable receipt for one order, as a PDF.
 *
 * Same view and same 80mm thermal paper size as the web version, so a receipt
 * printed from the phone is byte-for-byte the one printed from the browser.
 *
 * No permission gate, matching the web controller: front-of-house staff hand
 * receipts to customers regardless of their management permissions, and the
 * order is already scoped to this tenant's own database.
 *
 * `download` rather than `stream`, since a mobile client saves or shares the
 * file rather than displaying it inline in a browser tab.
 */
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
            // Printed in place of the business name when the shop has one.
            'logo' => app(BusinessLogo::class)->dataUri(),
        ])->setPaper([0, 0, 226.77, 566.93]);

        return $pdf->download("receipt-{$order->id}.pdf");
    }
}
