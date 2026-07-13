<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        {{-- dompdf notes (learned building receipts.transfer, same rules apply):
             1) <table> column-width calculation clips trailing characters of
                cell content, regardless of page width — avoid real <table>s
                here; use inline-block spans with fixed px widths instead.
             2) any text-align:end element with content flush against its
                container boundary loses its LAST character(s) (confirmed:
                the ₪ symbol specifically) — always add a small end-side
                padding buffer on such elements. --}}
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }
        .page { width: 100%; padding: 10mm; }
        .center { text-align: center; }
        .title { font-size: 20px; font-weight: bold; }
        .subtitle { font-size: 11px; color: #666; margin-top: 1mm; }
        .divider { border-top: 1px solid #ccc; margin: 5mm 0; }
        .dashed { border-top: 1px dashed #bbb; margin: 3mm 0; }

        .summary { width: 100%; }
        .summary-card { display: inline-block; width: 30%; vertical-align: top; padding-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }}: 3mm; }
        .summary-label { font-size: 9px; color: #666; }
        .summary-value { font-size: 18px; font-weight: bold; margin-top: 1mm; }

        .section-title { font-size: 13px; font-weight: bold; margin-bottom: 3mm; }

        .tx-head { font-size: 9px; color: #666; border-bottom: 1px solid #ccc; padding-bottom: 1.5mm; margin-bottom: 2mm; }
        .tx-row { padding: 1.5mm 0; border-bottom: 1px solid #eee; }
        .col-customer { display: inline-block; width: 30%; font-size: 10px; font-weight: bold; }
        .col-date { display: inline-block; width: 22%; font-size: 10px; color: #444; }
        .col-status { display: inline-block; width: 18%; font-size: 10px; color: #444; }
        .col-amount { display: inline-block; width: 27%; text-align: {{ app()->getLocale() === 'ar' ? 'left' : 'right' }}; font-size: 10px; font-weight: bold; padding-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }}: 3mm; }

        .footer { margin-top: 8mm; font-size: 9px; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="page">
        <div class="center">
            <div class="title">{{ $businessName }}</div>
            <div class="subtitle">{{ __('التقارير') }} — {{ \Illuminate\Support\Carbon::parse($from)->translatedFormat('j F Y') }} → {{ \Illuminate\Support\Carbon::parse($to)->translatedFormat('j F Y') }}</div>
        </div>

        <div class="divider"></div>

        <div class="summary">
            <div class="summary-card">
                <div class="summary-label">{{ __('عدد الطلبات') }}</div>
                <div class="summary-value" dir="ltr">{{ number_format($count) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">{{ __('إجمالي الإيرادات') }}</div>
                <div class="summary-value" dir="ltr">{{ number_format($total, 2) }} ₪</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">{{ __('متوسط الطلب') }}</div>
                <div class="summary-value" dir="ltr">{{ number_format($average, 2) }} ₪</div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="section-title">{{ __('طلبات الفترة') }} ({{ number_format($count) }})</div>

        @if ($transfers->isEmpty())
            <p style="color:#888; font-size: 10px;">{{ __('لا نشاط في هذه الفترة') }}</p>
        @else
            <div class="tx-head">
                <span class="col-customer">{{ __('العميل') }}</span>
                <span class="col-date">{{ __('التاريخ') }}</span>
                <span class="col-status">{{ __('الحالة') }}</span>
                <span class="col-amount">{{ __('المبلغ') }}</span>
            </div>
            @foreach ($transfers->sortByDesc('created_at') as $t)
                <div class="tx-row">
                    <span class="col-customer">{{ $t->customer }}</span>
                    <span class="col-date">{{ $t->created_at->translatedFormat('j M Y') }}</span>
                    <span class="col-status">
                        {{ match($t->status->value) { 'completed' => __('مكتملة'), 'pending' => __('قيد الانتظار'), default => __('فاشلة') } }}
                    </span>
                    <span class="col-amount" dir="ltr">{{ number_format($t->amount, 2) }} ₪</span>
                </div>
            @endforeach
        @endif

        <div class="footer">{{ __('حوالاتي') }} — {{ now()->translatedFormat('j F Y — H:i') }}</div>
    </div>
</body>
</html>
