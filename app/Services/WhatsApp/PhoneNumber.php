<?php

namespace App\Services\WhatsApp;

/**
 * Normalises operator-entered phone numbers to E.164 (+<country><subscriber>).
 *
 * Pure static helpers - no container, no database - so this is safe to call
 * from a FormRequest, a migration, and the sender alike.
 *
 * The awkward case this exists for: a user pastes their country code in front
 * of the local number without dropping the local trunk zero, giving
 * "+9720595817016" (13 digits - not valid E.164 for 972/970, which are country
 * code + 9 digits). Step 6 collapses that leftover zero.
 */
class PhoneNumber
{
    /** Arabic-Indic (U+0660-0669) and Extended Arabic-Indic (U+06F0-06F9) digits. */
    private const EASTERN_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const WESTERN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * Returns the number in E.164 form, or null when it cannot be made valid.
     * Idempotent: toE164(toE164($x)) === toE164($x).
     */
    public static function toE164(?string $raw, ?string $defaultCountryCode = null): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $defaultCountryCode ??= (string) config('whatsapp.default_country_code', '972');

        // 1. Arabic-Indic digits -> ASCII. Without this the \D+ strip below
        //    would delete them outright and silently destroy the number.
        $value = str_replace(self::EASTERN_DIGITS, self::WESTERN_DIGITS, trim($raw));

        // 2. Does the number state its own country code?
        $isInternational = str_starts_with($value, '+') || str_starts_with($value, '00');

        // 3. Keep digits only (drops +, spaces, dashes, parens, RTL/LTR marks).
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($value, '00')) {
            $digits = substr($digits, 2);
        }

        // 4. A bare local number gets the configured country code.
        if (! $isInternational) {
            if (str_starts_with($digits, '0')) {
                $digits = $defaultCountryCode.substr($digits, 1);
            } elseif (! self::startsWithKnownCountryCode($digits)) {
                $digits = $defaultCountryCode.$digits;
            }
        }

        // 5. Collapse a trunk zero left between the country code and the
        //    subscriber number (+972 0595817016 -> +972595817016).
        foreach (self::countryCodes() as $code) {
            if (str_starts_with($digits, $code.'0')) {
                $digits = $code.substr($digits, strlen($code) + 1);
                break;
            }
        }

        // 6. E.164: leading digit 1-9, total 8-15 digits.
        return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1 ? '+'.$digits : null;
    }

    /**
     * Meta's Cloud API wants the recipient as bare digits with no leading '+'.
     */
    public static function toWireFormat(string $e164): string
    {
        return ltrim($e164, '+');
    }

    /**
     * Last four digits only - for logs, so a failure is diagnosable without
     * writing a customer's full number to disk.
     */
    public static function mask(?string $raw): string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        return strlen($digits) < 4 ? '****' : '****'.substr($digits, -4);
    }

    /** @return array<int, string> longest first, so 9725 never shadows 972 */
    private static function countryCodes(): array
    {
        $codes = (array) config('whatsapp.country_codes', ['972', '970']);

        usort($codes, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        return array_map(strval(...), $codes);
    }

    private static function startsWithKnownCountryCode(string $digits): bool
    {
        foreach (self::countryCodes() as $code) {
            if (str_starts_with($digits, $code)) {
                return true;
            }
        }

        return false;
    }
}
