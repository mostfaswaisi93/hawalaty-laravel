<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Transfer;
use App\Services\Tenant\BusinessLogo;
use Illuminate\View\View;

/**
 * Printable receipt for a single order. Any authenticated tenant member may
 * print any order's receipt (no PermissionKey gate - matches history's read
 * access), since front-of-house staff hand receipts to customers regardless of
 * their management permissions.
 *
 * Served as HTML and printed by the BROWSER, whose text engine shapes Arabic
 * correctly - do NOT move this to dompdf, which has no Arabic shaping or bidi
 * and renders isolated letters in reverse. The view carries
 * `@page { size: 80mm auto }` for the thermal roll.
 *
 * Api\V1\ReceiptController still returns a PDF: a mobile client wants a file.
 */
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
            // The shop's own contact block, captured when the licence was sold.
            'address' => $license?->address,
            'phone' => $license?->phone,
            // Who is standing at the till. Stamped at render time, not stored:
            // reprinting the same order later is a NEW print by whoever did it.
            'printedBy' => auth('tenant')->user()?->name ?? __('حوالاتي'),
            'businessName' => $license?->customer_name ?? __('حوالاتي'),
            // The account's own symbol - the accounting side is multi-currency.
            // A cash order names no account, so it falls back to the shekel.
            'symbol' => $order->account?->currency?->symbol ?? '₪',
            // Printed in place of the business name when the shop has one.
            'logo' => app(BusinessLogo::class)->dataUri(),
        ]);
    }
}
