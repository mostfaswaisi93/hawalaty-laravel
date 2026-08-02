<?php

namespace App\Support;

/**
 * Vehicle Identification Number, per ISO 3779 and ISO 3780.
 *
 * Seventeen characters. The letters I, O and Q are excluded throughout so they
 * cannot be confused with 1 and 0. Positions 1-3 are the World Manufacturer
 * Identifier, position 9 is a check digit, and position 10 encodes the model
 * year on a thirty-year cycle.
 *
 * What is deliberately NOT attempted: model, engine or trim. Those live in
 * positions 4-8, which every manufacturer defines for itself, and reading them
 * needs a licensed database. Guessing would put a confident wrong answer in
 * front of someone about to order a part.
 */
final class Vin
{
    public const LENGTH = 17;

    /**
     * Position 10, model year. The cycle repeats every thirty years, so a code
     * is ambiguous between - for example - 1995 and 2025.
     */
    private const YEAR_CODES = [
        'A' => 1980, 'B' => 1981, 'C' => 1982, 'D' => 1983, 'E' => 1984, 'F' => 1985,
        'G' => 1986, 'H' => 1987, 'J' => 1988, 'K' => 1989, 'L' => 1990, 'M' => 1991,
        'N' => 1992, 'P' => 1993, 'R' => 1994, 'S' => 1995, 'T' => 1996, 'V' => 1997,
        'W' => 1998, 'X' => 1999, 'Y' => 2000,
        '1' => 2001, '2' => 2002, '3' => 2003, '4' => 2004, '5' => 2005,
        '6' => 2006, '7' => 2007, '8' => 2008, '9' => 2009,
    ];

    /**
     * Transliteration and weights for the position-9 check digit.
     */
    private const TRANSLITERATION = [
        'A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8,
        'J' => 1, 'K' => 2, 'L' => 3, 'M' => 4, 'N' => 5, 'P' => 7, 'R' => 9,
        'S' => 2, 'T' => 3, 'U' => 4, 'V' => 5, 'W' => 6, 'X' => 7, 'Y' => 8, 'Z' => 9,
    ];

    private const WEIGHTS = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

    public static function clean(string $vin): string
    {
        return strtoupper(preg_replace('/[^A-HJ-NPR-Z0-9]/i', '', $vin) ?? '');
    }

    public static function isWellFormed(string $vin): bool
    {
        return strlen(self::clean($vin)) === self::LENGTH;
    }

    public static function wmi(string $vin): ?string
    {
        $vin = self::clean($vin);

        return strlen($vin) >= 3 ? substr($vin, 0, 3) : null;
    }

    /**
     * Model year from position 10.
     *
     * The code repeats on a thirty-year cycle, so the older reading is only
     * returned when the newer one would be in the future.
     */
    public static function modelYear(string $vin): ?int
    {
        $vin = self::clean($vin);

        if (strlen($vin) < 10) {
            return null;
        }

        $base = self::YEAR_CODES[$vin[9]] ?? null;

        if ($base === null) {
            return null;
        }

        $later = $base + 30;

        return $later <= ((int) date('Y') + 1) ? $later : $base;
    }

    /**
     * Whether position 9 agrees with the rest.
     *
     * Mandatory in North America and widely honoured elsewhere, but not
     * universal - plenty of legitimate European and Asian VINs fail it. A false
     * result is a hint to re-read the number, never grounds to refuse it.
     */
    public static function checkDigitValid(string $vin): bool
    {
        $vin = self::clean($vin);

        if (strlen($vin) !== self::LENGTH) {
            return false;
        }

        $sum = 0;

        foreach (str_split($vin) as $index => $character) {
            $value = is_numeric($character)
                ? (int) $character
                : (self::TRANSLITERATION[$character] ?? 0);

            $sum += $value * self::WEIGHTS[$index];
        }

        $remainder = $sum % 11;
        $expected = $remainder === 10 ? 'X' : (string) $remainder;

        return $vin[8] === $expected;
    }

    /**
     * @return array{vin: string, valid: bool, wmi: ?string, year: ?int, checkDigit: bool}
     */
    public static function decode(string $vin): array
    {
        $clean = self::clean($vin);

        return [
            'vin' => $clean,
            'valid' => strlen($clean) === self::LENGTH,
            'wmi' => self::wmi($clean),
            'year' => self::modelYear($clean),
            'checkDigit' => self::checkDigitValid($clean),
        ];
    }
}
