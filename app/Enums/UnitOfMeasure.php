<?php

namespace App\Enums;

/**
 * Units an item can be bought and sold in.
 *
 * Codes are UN/CEFACT Recommendation 20, the code list EN 16931 and every
 * PEPPOL-derived e-invoicing regime expect on an invoice line. The X-prefixed
 * entries are Recommendation 21 packaging codes, which the same UNECE list
 * carries; they are here because stock is received in boxes far more often than
 * it is sold in them.
 *
 * Fixed platform data, not tenant-editable: a tenant inventing its own "kg"
 * would silently break any invoice that has to leave the building.
 */
enum UnitOfMeasure: string
{
    case Piece = 'H87';
    case Pair = 'PR';
    case Set = 'SET';
    case Box = 'XBX';
    case Pack = 'XPK';

    case Kilogram = 'KGM';
    case Gram = 'GRM';
    case Tonne = 'TNE';

    case Litre = 'LTR';
    case Millilitre = 'MLT';

    case Metre = 'MTR';
    case Centimetre = 'CMT';
    case SquareMetre = 'MTK';

    case Hour = 'HUR';
    case Minute = 'MIN';
    case Day = 'DAY';

    public function label(): string
    {
        return match ($this) {
            self::Piece => __('حبة'),
            self::Pair => __('زوج'),
            self::Set => __('طقم'),
            self::Box => __('كرتونة'),
            self::Pack => __('عبوة'),
            self::Kilogram => __('كيلوغرام'),
            self::Gram => __('غرام'),
            self::Tonne => __('طن'),
            self::Litre => __('لتر'),
            self::Millilitre => __('مليلتر'),
            self::Metre => __('متر'),
            self::Centimetre => __('سنتيمتر'),
            self::SquareMetre => __('متر مربع'),
            self::Hour => __('ساعة'),
            self::Minute => __('دقيقة'),
            self::Day => __('يوم'),
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Piece => __('حبة'),
            self::Pair => __('زوج'),
            self::Set => __('طقم'),
            self::Box => __('كرتونة'),
            self::Pack => __('عبوة'),
            self::Kilogram => __('كغم'),
            self::Gram => __('غم'),
            self::Tonne => __('طن'),
            self::Litre => __('لتر'),
            self::Millilitre => __('مل'),
            self::Metre => __('م'),
            self::Centimetre => __('سم'),
            self::SquareMetre => __('م²'),
            self::Hour => __('ساعة'),
            self::Minute => __('دقيقة'),
            self::Day => __('يوم'),
        };
    }

    /**
     * Decimal places a quantity in this unit is entered and stored with.
     *
     * Discrete units are whole-number only: half a phone is a data-entry error,
     * not a sale. Continuous ones carry three places, which is the resolution a
     * legal-for-trade scale or fuel meter reports.
     */
    public function decimals(): int
    {
        return $this->isDiscrete() ? 0 : 3;
    }

    public function isDiscrete(): bool
    {
        return in_array($this, [self::Piece, self::Pair, self::Set, self::Box, self::Pack], true);
    }

    /**
     * Smallest increment the quantity input accepts.
     */
    public function step(): string
    {
        return $this->isDiscrete() ? '1' : '0.001';
    }

    public function group(): string
    {
        return match ($this) {
            self::Piece, self::Pair, self::Set, self::Box, self::Pack => __('العدد والتعبئة'),
            self::Kilogram, self::Gram, self::Tonne => __('الوزن'),
            self::Litre, self::Millilitre => __('الحجم'),
            self::Metre, self::Centimetre, self::SquareMetre => __('الطول والمساحة'),
            self::Hour, self::Minute, self::Day => __('الوقت'),
        };
    }

    /**
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $case) {
            $groups[$case->group()][] = $case;
        }

        return $groups;
    }
}
