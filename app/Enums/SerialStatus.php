<?php

namespace App\Enums;

/**
 * Where one physical unit is in its life.
 */
enum SerialStatus: string
{
    case InStock = 'in_stock';
    case Sold = 'sold';
    case Returned = 'returned';
    case Scrapped = 'scrapped';

    public function label(): string
    {
        return match ($this) {
            self::InStock => __('في المخزون'),
            self::Sold => __('مُباع'),
            self::Returned => __('مرتجع'),
            self::Scrapped => __('متلَف'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::InStock => 'inventory_2',
            self::Sold => 'point_of_sale',
            self::Returned => 'assignment_return',
            self::Scrapped => 'delete_forever',
        };
    }

    /**
     * Whether a unit in this state can be sold. A returned unit can - it is back
     * on the shelf once it has been checked - but only after someone puts it
     * back in stock deliberately.
     */
    public function isAvailable(): bool
    {
        return $this === self::InStock;
    }
}
