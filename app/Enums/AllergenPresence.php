<?php

namespace App\Enums;

/**
 * How an allergen got into the item.
 *
 * The distinction is not cosmetic. "Contains" is a legal declaration about a
 * deliberate ingredient; "may contain" is a precautionary warning about a shared
 * fryer or a dusty worktop. Collapsing the two either over-warns until customers
 * stop believing the labels, or under-warns someone with a real allergy.
 */
enum AllergenPresence: string
{
    case Contains = 'contains';
    case MayContain = 'may_contain';

    public function label(): string
    {
        return match ($this) {
            self::Contains => __('يحتوي'),
            self::MayContain => __('قد يحتوي'),
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Contains => __('يحتوي'),
            self::MayContain => __('أثر'),
        };
    }

    /**
     * A deliberate ingredient outranks a trace, so merging two declarations of
     * the same allergen keeps the stronger one.
     */
    public function outranks(self $other): bool
    {
        return $this === self::Contains && $other === self::MayContain;
    }
}
