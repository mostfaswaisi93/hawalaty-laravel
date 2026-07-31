<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('فاتورة') }} #{{ strtoupper(substr($order->id, -8)) }}</title>

    {{--
        Rendered as HTML and printed by the BROWSER, not by dompdf.

        dompdf has no Arabic shaping or bidi: it drew the letters in isolated
        forms, left to right, so every Arabic receipt came out unreadable. The
        browser's text engine shapes Arabic correctly, and its print dialog
        talks to the thermal printer directly. The API still serves a PDF, for
        mobile clients that want a file rather than a page.

        Laid out to match a supermarket POS slip: a ruled grid throughout - a
        meta strip, a boxed customer line, a fully-bordered items table, a
        bordered totals stack, and the licence line last. 80mm is the standard
        thermal roll, and `size: 80mm auto` makes the page exactly as long as
        the content. Hairline rules rather than filled blocks: a thermal head
        burns a solid fill into a smear and wears itself out doing it.
    --}}
    {{-- Tajawal only, and no icon font: a print station is regularly offline,
         and this page draws no glyph that would justify shipping 447 kB to it. --}}
    <x-fonts :families="['tajawal']" :icons="false" />

    <style>
        /* Height is written in by script from the measured content - see the
           bottom of this file. `size: 80mm auto` is invalid CSS (a length cannot
           be paired with `auto`), so it was silently dropped and the slip
           printed on A4. This fallback keeps the width right if script is off. */
        @page { size: 80mm 200mm; margin: 0; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html { background: #eceff4; }

        body {
            /* Tajawal first, then whatever Arabic face the machine has - a print
               station is often offline, and a missing web font must not fall back
               to a Latin face that cannot draw Arabic at all. */
            font-family: 'Tajawal', 'Segoe UI', 'Noto Naskh Arabic', 'Geeza Pro', Tahoma, sans-serif;
            color: #000;
            -webkit-font-smoothing: antialiased;
        }

        .sheet {
            width: 80mm;
            margin: 20px auto;
            padding: 4mm 3mm;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .13);
        }

        /* ── the licence holder's own name, on top ── */
        .head { text-align: center; margin-bottom: 3mm; }
        .head h1 { font-size: 15pt; font-weight: 800; line-height: 1.15; }
        /* Capped by HEIGHT so a wide and a tall logo both sit in the same band. */
        .head .logo { max-height: 18mm; max-width: 62mm; object-fit: contain; }

        /* ── meta strip: label over value, columns across ── */
        .strip { display: flex; text-align: center; margin-bottom: 2mm; }
        .strip .col { flex: 1 1 0; min-width: 0; padding: 0 1mm; }
        .strip .k { font-size: 7.5pt; font-weight: 700; }
        .strip .v { font-size: 8.5pt; font-weight: 700; margin-top: .3mm; overflow-wrap: anywhere; }

        /* ── boxed customer line ── */
        .box {
            border: 1px solid #000; border-radius: 2mm;
            padding: 1.2mm 2mm; margin-bottom: 2mm;
            display: flex; justify-content: space-between; gap: 3mm;
            font-size: 8.5pt; font-weight: 700;
        }
        .box.plain { display: block; text-align: center; font-weight: 400; line-height: 1.6; }

        /* ── items: a full grid, like the POS slip ── */
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 1mm .8mm; font-size: 8pt; }
        th { font-weight: 700; text-align: center; }
        .c-no { width: 7mm; text-align: center; }
        .c-name { text-align: start; font-weight: 600; overflow-wrap: anywhere; }
        .c-qty, .c-price, .c-total { text-align: center; width: 12mm; }

        /* ── totals ──
              The AMOUNTS continue the table's last column: same ruled cell, same
              size, so the eye runs straight down 25 / 5 / 20. The LABELS carry no
              rule at all and are all one weight and size - no row shouts over the
              others. The first cell is the print stamp, sitting under the table
              on the opposite side, exactly like a POS slip. */
        .totals { width: 100%; border-collapse: collapse; margin-top: -1px; }
        .totals td { border: 0; padding: 1mm .8mm; font-size: 8pt; }
        /* The note takes ALL the slack, so the label sits tight against its own
           amount at the end edge instead of drifting into the middle. */
        .totals .note { text-align: start; font-size: 7pt; vertical-align: top; line-height: 1.5; }
        .totals .lbl { text-align: end; white-space: nowrap; width: 24mm; padding-inline-end: 1.5mm; }
        /* Same 12mm as .c-total above it. */
        .totals .amt { border: 1px solid #000; text-align: center; width: 12mm; }

        /* Latin/numeric runs keep their own direction inside the RTL page. */
        .ltr { direction: ltr; unicode-bidi: isolate; display: inline-block; }

        /* Small, off to the side - it is a provenance note, not a message to the customer. */
        .licence { margin-top: 2mm; text-align: start; font-size: 6.5pt; color: #333; }

        /* ── on-screen only ── */
        .toolbar { width: 80mm; margin: 0 auto; display: flex; gap: 8px; }
        .toolbar button, .toolbar a {
            flex: 1; padding: 10px 12px; border: 0; border-radius: 10px;
            font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer;
            text-align: center; text-decoration: none;
        }
        .toolbar .print { background: #00236f; color: #fff; }
        .toolbar .back { background: #fff; color: #00236f; border: 1px solid #c9cedb; }

        @media print {
            html, body { background: #fff; }
            .sheet { width: auto; margin: 0; padding: 2mm 2.5mm 3mm; box-shadow: none; }
            .toolbar { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="print" onclick="window.print()">{{ __('طباعة') }}</button>
        <a class="back" href="{{ route('history.index') }}">{{ __('رجوع') }}</a>
    </div>

    <div class="sheet">
        {{-- The shop's own logo when it has uploaded one, its name otherwise.
             `alt` still carries the name, so a logo that fails to decode on the
             printer never leaves the slip anonymous. --}}
        <div class="head">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $businessName }}" class="logo" />
            @else
                <h1>{{ $businessName }}</h1>
            @endif
        </div>

        <div class="strip">
            <div class="col">
                <div class="k">{{ __('بيع') }}</div>
                <div class="v">&nbsp;</div>
            </div>
            <div class="col">
                <div class="k">{{ __('رقم') }}</div>
                <div class="v"><span class="ltr">{{ strtoupper(substr($order->id, -6)) }}</span></div>
            </div>
            <div class="col">
                <div class="k">{{ __('الوقت') }}</div>
                <div class="v"><span class="ltr">{{ $order->created_at->format('H:i:s') }}</span></div>
            </div>
            <div class="col">
                <div class="k">{{ __('التاريخ') }}</div>
                <div class="v"><span class="ltr">{{ $order->created_at->format('d/m/Y') }}</span></div>
            </div>
        </div>

        <div class="box">
            <span>{{ __('الإسم') }}: {{ $order->customer }}</span>
            @if ($order->customerProfile?->phone)
                <span class="ltr">{{ $order->customerProfile->phone }}</span>
            @endif
        </div>

        @if ($order->lineItems->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th class="c-no">{{ __('الرقم') }}</th>
                        <th class="c-name">{{ __('إسم الصنف') }}</th>
                        <th class="c-qty">{{ __('كمية') }}</th>
                        <th class="c-price">{{ __('سعر') }}</th>
                        <th class="c-total">{{ __('الأجمالي') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->lineItems as $line)
                        <tr>
                            <td class="c-no"><span class="ltr">{{ $loop->iteration }}</span></td>
                            <td class="c-name">{{ $line->name }}</td>
                            <td class="c-qty"><span class="ltr">{{ number_format($line->quantity, 2) }}</span></td>
                            <td class="c-price"><span class="ltr">{{ number_format($line->price, 2) }}</span></td>
                            <td class="c-total"><span class="ltr">{{ number_format($line->quantity * $line->price, 2) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @php
            // The subtotal is summed from the LINE ITEMS, not derived as
            // amount + discount: the lines are what the table above prints, so
            // this is the one figure that can never disagree with them.
            $subtotal = $order->lineItems->sum(fn ($line) => $line->quantity * (float) $line->price);
        @endphp
        @php
            // The stamp cell spans every totals row, so it stays level with them
            // however many optional lines this order happens to have.
            $totalRows = 2
                + ($order->discount > 0 ? 1 : 0)
                + ($order->transferred_amount ? 1 : 0)
                + ($order->has_change && $order->change_amount ? 1 : 0);
        @endphp
        <table class="totals">
            <tr>
                <td class="note" rowspan="{{ $totalRows }}">{{ __('طبعه') }}: {{ $printedBy }} {{ __('الساعة') }}: <span class="ltr">{{ now()->format('d/m/Y H:i:s') }}</span></td>
                <td class="lbl">{{ __('الاجمالي') }}</td>
                <td class="amt"><span class="ltr">{{ number_format($subtotal, 2) }}</span></td>
            </tr>
            @if ($order->discount > 0)
                <tr>
                    <td class="lbl">{{ __('خصم') }}</td>
                    <td class="amt"><span class="ltr">{{ number_format($order->discount, 2) }}</span></td>
                </tr>
            @endif
            <tr>
                <td class="lbl">{{ __('صافي الفاتورة') }}</td>
                <td class="amt"><span class="ltr">{{ number_format($order->amount, 2) }}</span></td>
            </tr>
            @if ($order->transferred_amount)
                <tr>
                    <td class="lbl">{{ __('المبلغ المدفوع') }}</td>
                    <td class="amt"><span class="ltr">{{ number_format($order->transferred_amount, 2) }}</span></td>
                </tr>
            @endif
            @if ($order->has_change && $order->change_amount)
                <tr>
                    <td class="lbl">{{ __('الباقي') }}</td>
                    <td class="amt"><span class="ltr">{{ number_format($order->change_amount, 2) }}</span></td>
                </tr>
            @endif
        </table>

        @if (filled($address) || filled($phone))
            <div class="box plain" style="margin-top: 2mm;">
                @if (filled($address)){{ $address }}@endif
                @if (filled($phone))
                    @if (filled($address))<br>@endif{{ __('جوال') }}: <span class="ltr">{{ $phone }}</span>
                @endif
            </div>
        @endif

        <div class="licence">
            {{ __('نسخة مرخصة من حوالاتي لـ') }} {{ $businessName }}
        </div>
    </div>

    <script>
        (function () {
            const sheet = document.querySelector('.sheet');
            if (!sheet) return;

            const style = document.createElement('style');
            document.head.appendChild(style);

            // The roll is continuous: the page must be exactly as long as the
            // receipt, or the printer feeds (and the operator tears off) a strip
            // of blank paper after every sale.
            const fit = () => {
                const mm = sheet.getBoundingClientRect().height * 25.4 / 96;
                style.textContent = '@page { size: 80mm ' + Math.ceil(mm + 4) + 'mm; margin: 0; }';
            };

            fit();
            // Re-measured on demand: a late web font changes the height, and
            // Ctrl+P must get the same page as the button does.
            window.addEventListener('beforeprint', fit);
            if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
        })();
    </script>
</body>
</html>
