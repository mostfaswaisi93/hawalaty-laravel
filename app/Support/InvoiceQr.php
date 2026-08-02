<?php

namespace App\Support;

/**
 * The TLV payload a compliant invoice QR carries.
 *
 * Tag-Length-Value, base64 encoded - the encoding ZATCA specifies and that the
 * other regimes in the region have followed. Five mandatory fields:
 *
 *   1  seller name
 *   2  seller tax registration number
 *   3  timestamp, ISO 8601
 *   4  invoice total including tax
 *   5  tax amount
 *
 * Length is a single byte, so a value longer than 255 bytes cannot be encoded -
 * it is truncated on a character boundary rather than cut mid-sequence, which
 * would produce invalid UTF-8 in the seller's name.
 *
 * This produces the payload only. Turning it into an image needs a QR library,
 * which this application does not carry; the string is what a renderer or a
 * printer driver is handed.
 */
final class InvoiceQr
{
    private const MAX_VALUE_BYTES = 255;

    /**
     * @param  array<int, string>  $extra  additional tag => value pairs
     */
    public static function payload(
        string $sellerName,
        string $taxNumber,
        string $timestamp,
        string $totalWithTax,
        string $taxAmount,
        array $extra = [],
    ): string {
        $fields = [
            1 => $sellerName,
            2 => $taxNumber,
            3 => $timestamp,
            4 => $totalWithTax,
            5 => $taxAmount,
        ] + $extra;

        $encoded = '';

        foreach ($fields as $tag => $value) {
            $encoded .= self::tlv((int) $tag, (string) $value);
        }

        return base64_encode($encoded);
    }

    /**
     * Decode back to tag => value. Exists so the payload can be checked rather
     * than taken on trust - a QR nobody has ever read back is a QR nobody knows
     * is right.
     *
     * @return array<int, string>
     */
    public static function decode(string $payload): array
    {
        $raw = base64_decode($payload, true);

        if ($raw === false) {
            return [];
        }

        $fields = [];
        $offset = 0;
        $length = strlen($raw);

        while ($offset + 2 <= $length) {
            $tag = ord($raw[$offset]);
            $size = ord($raw[$offset + 1]);
            $offset += 2;

            if ($offset + $size > $length) {
                break;
            }

            $fields[$tag] = substr($raw, $offset, $size);
            $offset += $size;
        }

        return $fields;
    }

    private static function tlv(int $tag, string $value): string
    {
        // Byte length, not character count: the length byte counts octets, and
        // Arabic text is multi-byte.
        if (strlen($value) > self::MAX_VALUE_BYTES) {
            $value = mb_strcut($value, 0, self::MAX_VALUE_BYTES);
        }

        return chr($tag).chr(strlen($value)).$value;
    }
}
