<?php

namespace App\Services\WhatsApp;

class PhoneNumber
{
    private const EASTERN_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const WESTERN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public static function toE164(?string $raw, ?string $defaultCountryCode = null): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $defaultCountryCode ??= (string) config('whatsapp.default_country_code', '972');

        $value = str_replace(self::EASTERN_DIGITS, self::WESTERN_DIGITS, trim($raw));

        $isInternational = str_starts_with($value, '+') || str_starts_with($value, '00');

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($value, '00')) {
            $digits = substr($digits, 2);
        }

        if (! $isInternational) {
            if (str_starts_with($digits, '0')) {
                $digits = $defaultCountryCode.substr($digits, 1);
            } elseif (! self::startsWithKnownCountryCode($digits)) {
                $digits = $defaultCountryCode.$digits;
            }
        }

        foreach (self::countryCodes() as $code) {
            if (str_starts_with($digits, $code.'0')) {
                $digits = $code.substr($digits, strlen($code) + 1);
                break;
            }
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1 ? '+'.$digits : null;
    }

    public static function toWireFormat(string $e164): string
    {
        return ltrim($e164, '+');
    }

    public static function mask(?string $raw): string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        return strlen($digits) < 4 ? '****' : '****'.substr($digits, -4);
    }

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
