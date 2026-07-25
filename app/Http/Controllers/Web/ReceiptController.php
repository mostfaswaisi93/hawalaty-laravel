<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Transfer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Printable PDF receipt for a single order. Any authenticated tenant member
 * may print any order's receipt (no PermissionKey gate - matches history's
 * read access), since front-of-house staff need to hand receipts to
 * customers regardless of their management permissions.
 */
class ReceiptController extends Controller
{
    public function show(string $transfer): Response
    {
        $order = Transfer::with(['lineItems', 'bank', 'changeBank'])->findOrFail($transfer);

        $license = app()->bound('currentLicense') ? app('currentLicense') : null;

        $pdf = Pdf::loadView('receipts.transfer', [
            'order' => $order,
            'businessName' => $license?->customer_name ?? __('حوالاتي'),
        ])->setPaper([0, 0, 226.77, 566.93]); // 80mm thermal-receipt width, tall enough for most orders

        return $pdf->stream("receipt-{$order->id}.pdf");
    }
}
