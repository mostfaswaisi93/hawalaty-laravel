<?php

namespace App\Enums;

/**
 * Tax category, per UN/EDIFACT code list 5305.
 *
 * The codes EN 16931 puts on every invoice line. A zero rate and an exemption
 * both charge nothing and are legally different: zero-rated supplies stay inside
 * the tax system and let the seller reclaim input tax, exempt ones do not. An
 * invoice that cannot tell them apart is not compliant, and the rate alone
 * cannot - both are 0.
 */
enum TaxCategory: string
{
    case Standard = 'S';
    case ZeroRated = 'Z';
    case Exempt = 'E';
    case OutOfScope = 'O';
    case ReverseCharge = 'AE';

    public function label(): string
    {
        return match ($this) {
            self::Standard => __('نسبة أساسية'),
            self::ZeroRated => __('نسبة صفرية'),
            self::Exempt => __('معفى'),
            self::OutOfScope => __('خارج النطاق'),
            self::ReverseCharge => __('الاحتساب العكسي'),
        };
    }

    /**
     * Why nothing was charged. EN 16931 requires a reason wherever the category
     * is not the standard rate.
     */
    public function exemptionReason(): ?string
    {
        return match ($this) {
            self::Standard => null,
            self::ZeroRated => __('توريد خاضع لنسبة صفرية'),
            self::Exempt => __('توريد معفى من الضريبة'),
            self::OutOfScope => __('خارج نطاق الضريبة'),
            self::ReverseCharge => __('الضريبة على المستورد - احتساب عكسي'),
        };
    }

    public function chargesTax(): bool
    {
        return $this === self::Standard;
    }

    /**
     * Best guess when nobody has said. A positive rate can only be the standard
     * category; a zero one is ambiguous, and zero-rated is the safer reading
     * because it keeps the supply inside the tax system.
     */
    public static function inferFrom(float $rate): self
    {
        return $rate > 0 ? self::Standard : self::ZeroRated;
    }
}
