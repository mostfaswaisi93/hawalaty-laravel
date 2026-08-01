<?php

namespace App\Services\WhatsApp;

use App\Models\Tenant\Transfer;
use App\Models\Tenant\TransferLineItem;

class NewOrderMessage
{
    private const LRI = "\u{2066}";

    private const PDI = "\u{2069}";

    private const DASH = '—';

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

        if (filled($destination = self::destinationName($transfer))) {
            $lines[] = 'البنك: '.$destination;
        }

        if (filled($transfer->transferred_amount)) {
            $lines[] = 'المبلغ المُحوَّل: '.self::money($transfer->transferred_amount);
        }

        if (filled($confirmUrl)) {
            $lines[] = '';
            $lines[] = 'لتأكيد استلام الحوالة أو الإبلاغ عن عدم وصولها، افتح الرابط:';
            $lines[] = self::isolate($confirmUrl);
        }

        return implode("\n", $lines);
    }

    public static function forVariables(Transfer $transfer, ?string $confirmUrl, int $variables): array
    {
        return $variables <= 1
            ? [self::singleLine($transfer, $confirmUrl)]
            : self::parameters($transfer, $confirmUrl);
    }

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

    private static function destinationName(Transfer $transfer): ?string
    {
        return $transfer->relationLoaded('account') && $transfer->account
            ? $transfer->account->label()
            : null;
    }

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

    private static function orDash(?string $value): string
    {
        $value = self::oneLine((string) $value);

        return $value !== '' ? $value : self::DASH;
    }

    private static function oneLine(?string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', (string) $value);
        $value = preg_replace('/ {2,}/', ' ', $value) ?? $value;

        return trim($value);
    }

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

    private static function money(mixed $value): string
    {
        return self::isolate(number_format((float) $value, 2).' ₪');
    }

    private static function isolate(string $value): string
    {
        return self::LRI.$value.self::PDI;
    }
}
