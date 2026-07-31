<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        {{-- dompdf: no external stylesheets/fonts - everything must be inline
             and use a font it ships with (DejaVu Sans covers Arabic + Latin
             correctly, verified). Meta rows are STACKED <div> blocks, not a
             two-column <table> - dompdf's table column-width calculation
             reliably clips the last few characters of narrow-column cell
             content regardless of page width (verified: identical clipping
             on an 80mm receipt AND a full A4 page), so single-column block
             flow is used for anything that isn't genuinely tabular. --}}
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }
        .receipt { width: 100%; padding: 4mm; }
        .center { text-align: center; }
        .business-name { font-size: 15px; font-weight: bold; }
        .muted { color: #666; font-size: 9px; }
        .divider { border-top: 1px dashed #999; margin: 3mm 0; }
        .row { margin-bottom: 2mm; }
        .row .label { color: #666; font-size: 9px; }
        .row .value { font-size: 11px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .items-label { font-size: 9px; color: #666; padding-bottom: 1mm; border-bottom: 1px solid #ccc; margin-bottom: 2mm; }
        .item-row { margin-bottom: 2mm; }
        .item-name { font-size: 10px; }
        {{-- dompdf clips the trailing character(s) of any text-align:end line
             when the content sits flush against the container edge (verified:
             the ₪ symbol specifically vanished at the end of these lines,
             confirmed via raw-HTML inspection that Blade emits it correctly
             - dompdf drops it at render time). A small end-side padding
             buffer reliably prevents it. --}}
        .item-detail { font-size: 10px; color: #444; text-align: {{ app()->getLocale() === 'ar' ? 'left' : 'right' }}; padding-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }}: 3mm; }
        .total-row td { padding-top: 2mm; font-size: 13px; font-weight: bold; }
        .amount-cell { padding-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }}: 3mm; text-align: {{ app()->getLocale() === 'ar' ? 'left' : 'right' }}; }
        .footer { margin-top: 5mm; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $businessName }}" style="max-height: 18mm; max-width: 60mm;" />
            @else
                <div class="business-name">{{ $businessName }}</div>
            @endif
            <div class="muted">{{ __('إيصال طلب') }}</div>
        </div>

        <div class="divider"></div>

        <div class="row">
            <div class="label">{{ __('رقم الطلب') }}</div>
            <div class="value" dir="ltr">#{{ strtoupper(substr($order->id, -8)) }}</div>
        </div>
        <div class="row">
            <div class="label">{{ __('التاريخ') }}</div>
            <div class="value">{{ $order->created_at->translatedFormat('j F Y - H:i') }}</div>
        </div>
        <div class="row">
            <div class="label">{{ __('العميل') }}</div>
            <div class="value">{{ $order->customer }}</div>
        </div>
        <div class="row">
            <div class="label">{{ __('طريقة الدفع') }}</div>
            <div class="value">{{ $order->payment_method->value === 'cash' ? __('نقدي') : __('تحويل بنكي') }}</div>
        </div>
        @if ($order->payment_method->value === 'bank' && $order->account)
            <div class="row">
                <div class="label">{{ __('البنك') }}</div>
                <div class="value">{{ $order->account->label() }}</div>
            </div>
        @endif
        <div class="row">
            <div class="label">{{ __('الحالة') }}</div>
            <div class="value">
                {{ match($order->status->value) { 'completed' => __('مكتملة'), 'pending' => __('قيد الانتظار'), default => __('فاشلة') } }}
            </div>
        </div>

        <div class="divider"></div>

        @if ($order->lineItems->isNotEmpty())
            <div class="items-label">{{ __('الأصناف') }}</div>
            @foreach ($order->lineItems as $line)
                <div class="item-row">
                    <div class="item-name">{{ $line->name }}</div>
                    <div class="item-detail" dir="ltr">×{{ $line->quantity }} &nbsp;&nbsp; {{ number_format($line->quantity * $line->price, 2) }} {{ $symbol }}</div>
                </div>
            @endforeach
            <div class="divider"></div>
        @endif

        <div class="row" style="margin-bottom: 0;">
            <table>
                <tr class="total-row">
                    <td>{{ __('الإجمالي') }}</td>
                    <td class="amount-cell"><span dir="ltr">{{ number_format($order->amount, 2) }} {{ $symbol }}</span></td>
                </tr>
                @if ($order->has_change && $order->change_amount)
                    <tr>
                        <td class="muted">{{ __('الباقي') }}</td>
                        <td class="muted amount-cell"><span dir="ltr">{{ number_format($order->change_amount, 2) }} {{ $symbol }}</span></td>
                    </tr>
                @endif
            </table>
        </div>

        <div class="divider"></div>

        <div class="center footer">
            {{ __('شكراً لتعاملكم معنا') }}<br>
            {{ __('حوالاتي') }}
        </div>
    </div>
</body>
</html>
