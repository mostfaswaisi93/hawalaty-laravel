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
 * operator - a failure invisible to any browser-based test.
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

    /** Placeholder for an absent optional: Meta rejects an empty body parameter. */
    private const DASH = '—';

    /**
     * How many body parameters the cloud template expects, i.e. the count of
     * {{1}}..{{N}} in the approved template. parameters() MUST return exactly
     * this many, in this order. Kept as a named constant so the test-send and
     * any future template change have one place to agree on.
     */
    public const PARAM_COUNT = 8;

    public static function build(Transfer $transfer, ?string $confirmUrl = null): string
    {
        $lines = [
            'طلب جديد',
            '',
            'رقم الطلب: '.self::isolate('#'.strtoupper(substr((string) $transfer->id, -8))),
            'التاريخ: '.self::isolate(($transfer->created_at ?? now())->locale('ar')->translatedFormat('j F Y - H:i')),
            'العميل: '.$transfer->customer,
        ];

        // Customer phone, only when the order captured one. Identity IS the
        // number (see CustomerService::findOrCreate), so it lives on the linked
        // profile - customer_id, and therefore customerProfile, is null when the
        // order carried no phone. relationLoaded() guards a lazy load the same
        // way `bank` does below: this can run from a context whose `tenant`
        // connection is no longer pointed anywhere useful.
        if ($transfer->relationLoaded('customerProfile')
            && $transfer->customerProfile
            && filled($transfer->customerProfile->phone)) {
            $lines[] = 'الهاتف: '.self::isolate($transfer->customerProfile->phone);
        }

        $lines[] = '';

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
        if (filled($destination = self::destinationName($transfer))) {
            $lines[] = 'البنك: '.$destination;
        }

        // `sender_name` / `sender_phone` are deliberately NOT included: the
        // recipient is the bank that received the transfer, and the operator
        // considers those two fields noise in the notification. They are still
        // captured on the order form, stored, and printed on the receipt.
        //
        // `transferred_amount` IS kept - it is the figure the bank reconciles
        // against, and it can legitimately differ from the order total.
        // Nullable in both schema and validation, so emit the label only when
        // there is a value; never render the literal string "null".
        if (filled($transfer->transferred_amount)) {
            $lines[] = 'المبلغ المُحوَّل: '.self::money($transfer->transferred_amount);
        }

        // The confirmation link: the bank account owner opens it to confirm the
        // money arrived or report that it did not. Isolated like every other
        // Latin run so the URL keeps its direction inside the RTL paragraph.
        if (filled($confirmUrl)) {
            $lines[] = '';
            $lines[] = 'لتأكيد استلام الحوالة أو الإبلاغ عن عدم وصولها، افتح الرابط:';
            $lines[] = self::isolate($confirmUrl);
        }

        return implode("\n", $lines);
    }

    /**
     * The ordered, SINGLE-LINE values for the multi-variable cloud template.
     *
     * Meta rejects a newline (or tab, or 5+ consecutive spaces) inside ANY body
     * parameter with error 132018 - verified against the live number. So the
     * message layout's line breaks live in the approved template's STATIC text,
     * and every value here is forced to one line by oneLine(). The order MUST
     * match {{1}}..{{PARAM_COUNT}} in that template.
     *
     * Every slot is non-empty: Meta also rejects an empty parameter, so an
     * absent optional (no phone, no bank, no link) becomes a dash, never "".
     *
     * @return array<int, string>
     */
    /**
     * The body parameters for whichever template is configured: the full
     * multi-field set, or a single flattened line for a legacy one-parameter
     * template (e.g. while the multi-field template awaits Meta approval).
     * $variables is WhatsAppSettings::variableCount().
     *
     * @return array<int, string>
     */
    public static function forVariables(Transfer $transfer, ?string $confirmUrl, int $variables): array
    {
        return $variables <= 1
            ? [self::singleLine($transfer, $confirmUrl)]
            : self::parameters($transfer, $confirmUrl);
    }

    /**
     * The whole order on ONE line, for a single-parameter template. Built from
     * the same per-field values, dashes dropped, so no label reads "الهاتف: —".
     * oneLine() guarantees Meta accepts it (no newline -> no 132018).
     */
    public static function singleLine(Transfer $transfer, ?string $confirmUrl = null): string
    {
        $p = self::parameters($transfer, $confirmUrl);

        $parts = array_filter([
            'طلب '.$p[0],
            'العميل: '.$p[2],
            $p[3] !== self::DASH ? 'الهاتف: '.$p[3] : null,
            'الطلب: '.$p[4],
            'الإجمالي: '.$p[5],
            $p[6] !== self::DASH ? 'البنك: '.$p[6] : null,
            $p[7] !== self::DASH ? 'للتأكيد افتح: '.$p[7] : null,
        ]);

        return self::oneLine(implode(' — ', $parts));
    }

    public static function parameters(Transfer $transfer, ?string $confirmUrl = null): array
    {
        $phone = ($transfer->relationLoaded('customerProfile') && $transfer->customerProfile)
            ? $transfer->customerProfile->phone
            : null;

        return [
            self::isolate('#'.strtoupper(substr((string) $transfer->id, -8))),
            self::isolate(($transfer->created_at ?? now())->locale('ar')->translatedFormat('j F Y - H:i')),
            self::orDash($transfer->customer),
            filled($phone) ? self::isolate(self::oneLine($phone)) : self::DASH,
            self::orDash(self::itemsSummary($transfer)),
            self::money($transfer->amount),
            self::orDash(self::destinationName($transfer)),
            filled($confirmUrl) ? self::isolate(self::oneLine($confirmUrl)) : self::DASH,
        ];
    }

    /**
     * What to call the place the money went: the chosen Account, named
     * "<institution> - <holder>" so the recipient recognises which of their
     * accounts this is.
     *
     * relationLoaded() guards a lazy load - this can run from a context whose
     * `tenant` connection is no longer pointed anywhere useful.
     */
    private static function destinationName(Transfer $transfer): ?string
    {
        return $transfer->relationLoaded('account') && $transfer->account
            ? $transfer->account->label()
            : null;
    }

    /**
     * Sample values for the settings "test send", which targets the same
     * multi-variable template and so must supply the same number of parameters.
     * The typed note lands in the order slot; everything else is a placeholder.
     *
     * @return array<int, string>
     */
    public static function testParameters(?string $note = null, int $variables = self::PARAM_COUNT): array
    {
        $note = self::oneLine((string) $note);

        if ($variables <= 1) {
            return [$note !== '' ? $note : 'رسالة تجريبية من حوالاتي للتأكد من إعدادات واتساب.'];
        }

        return [
            self::isolate('#TEST0001'),
            self::isolate(now()->locale('ar')->translatedFormat('j F Y - H:i')),
            'اختبار',
            self::DASH,
            $note !== '' ? $note : 'رسالة تجريبية من حوالاتي',
            self::money(0),
            self::DASH,
            self::DASH,
        ];
    }

    /**
     * The line items as ONE line ("، "-separated), or the denormalised
     * `item_name` summary when the relation is not loaded - never empty-string
     * back to the caller, which turns it into a dash.
     */
    private static function itemsSummary(Transfer $transfer): string
    {
        if ($transfer->relationLoaded('lineItems') && $transfer->lineItems->isNotEmpty()) {
            return $transfer->lineItems
                ->map(fn (TransferLineItem $line) => $line->name
                    .' ×'.self::isolate((string) $line->quantity)
                    .' - '.self::money($line->quantity * (float) $line->price))
                ->implode('، ');
        }

        return (string) $transfer->item_name;
    }

    /** Non-empty single line, or the dash placeholder. */
    private static function orDash(?string $value): string
    {
        $value = self::oneLine((string) $value);

        return $value !== '' ? $value : self::DASH;
    }

    /**
     * Collapses everything Meta forbids in a body parameter - newlines, tabs and
     * runs of spaces - to a single space, then trims. The bidi isolate marks
     * (U+2066/U+2069) are not whitespace and survive untouched.
     */
    private static function oneLine(?string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', (string) $value);
        $value = preg_replace('/ {2,}/', ' ', $value) ?? $value;

        return trim($value);
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
                    .' - '.self::money($line->quantity * (float) $line->price))
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
