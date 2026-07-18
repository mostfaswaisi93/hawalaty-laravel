<?php

namespace App\Services\WhatsApp;

use App\Models\Tenant\Transfer;
use App\Models\Tenant\TransferLineItem;

/**
 * Renders the Arabic WhatsApp body for a newly created bank/wallet order.
 *
 * The Arabic is hardcoded rather than routed through __(). App\Http\Middleware\SetLocale
 * only runs on the `web` middleware group and APP_LOCALE is `en`, so an order
 * created through the REST API or the offline-sync endpoint would otherwise
 * render this message in English and send it to an Arabic-speaking bank
 * operator — a failure invisible to any browser-based test.
 *
 * Every Latin/numeric run is wrapped in Unicode isolates (LRI U+2066 … PDI
 * U+2069). WhatsApp lays the body out as a single RTL paragraph; without the
 * isolates a trailing "₪" or a leading "+" visually jumps to the wrong end of
 * its number.
 */
class NewOrderMessage
{
    private const LRI = "\u{2066}";

    private const PDI = "\u{2069}";

    public static function build(Transfer $transfer): string
    {
        $lines = [
            'طلب جديد',
            '',
            'رقم الطلب: '.self::isolate('#'.strtoupper(substr((string) $transfer->id, -8))),
            'التاريخ: '.self::isolate(($transfer->created_at ?? now())->locale('ar')->translatedFormat('j F Y — H:i')),
            'العميل: '.$transfer->customer,
            '',
        ];

        foreach (self::itemLines($transfer) as $index => $itemLine) {
            if ($index === 0) {
                $lines[] = 'الأصناف:';
            }

            $lines[] = $itemLine;
        }

        $lines[] = '';
        $lines[] = 'الإجمالي: '.self::money($transfer->amount);
        $lines[] = '';
        $lines[] = 'طريقة الدفع: تحويل بنكي';

        // relationLoaded() guards a lazy load: this can run from a context whose
        // `tenant` connection is no longer pointed anywhere useful.
        if ($transfer->relationLoaded('bank') && $transfer->bank) {
            $lines[] = 'البنك: '.$transfer->bank->name;
        }

        // All three are nullable in both the schema and the validation rules —
        // emit the label only when there is a value, never the string "null".
        if (filled($transfer->sender_name)) {
            $lines[] = 'اسم المُرسِل: '.$transfer->sender_name;
        }

        if (filled($transfer->sender_phone)) {
            $lines[] = 'هاتف المُرسِل: '.self::isolate((string) $transfer->sender_phone);
        }

        if (filled($transfer->transferred_amount)) {
            $lines[] = 'المبلغ المُحوَّل: '.self::money($transfer->transferred_amount);
        }

        return implode("\n", $lines);
    }

    /**
     * Falls back to the denormalised `item_name` summary column when the line
     * items are not loaded, so the message is never empty.
     *
     * @return array<int, string>
     */
    private static function itemLines(Transfer $transfer): array
    {
        if ($transfer->relationLoaded('lineItems') && $transfer->lineItems->isNotEmpty()) {
            return $transfer->lineItems
                ->map(fn (TransferLineItem $line) => '• '.$line->name
                    .' ×'.self::isolate((string) $line->quantity)
                    .' — '.self::money($line->quantity * (float) $line->price))
                ->all();
        }

        return filled($transfer->item_name) ? ['• '.$transfer->item_name] : [];
    }

    /**
     * The (float) cast is required: `amount` and friends are cast `decimal:2`,
     * which hands back a string.
     */
    private static function money(mixed $value): string
    {
        return self::isolate(number_format((float) $value, 2).' ₪');
    }

    private static function isolate(string $value): string
    {
        return self::LRI.$value.self::PDI;
    }
}
