<?php

namespace App\Enums;

/**
 * Why stock moved. The sign of the quantity says which way; this says what for.
 */
enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case ReturnIn = 'return_in';
    case ReturnOut = 'return_out';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';
    case Waste = 'waste';
    case Count = 'count';
    case Production = 'production';
    case Consumption = 'consumption';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => __('استلام مشتريات'),
            self::Sale => __('بيع'),
            self::ReturnIn => __('مرتجع من عميل'),
            self::ReturnOut => __('مرتجع إلى مورد'),
            self::Adjustment => __('تسوية يدوية'),
            self::Transfer => __('نقل بين المواقع'),
            self::Waste => __('تلف وفاقد'),
            self::Count => __('فرق جرد'),
            self::Production => __('إنتاج'),
            self::Consumption => __('استهلاك مكوّنات'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Purchase => 'local_shipping',
            self::Sale => 'point_of_sale',
            self::ReturnIn => 'assignment_return',
            self::ReturnOut => 'undo',
            self::Adjustment => 'tune',
            self::Transfer => 'swap_horiz',
            self::Waste => 'delete_sweep',
            self::Count => 'checklist',
            self::Production => 'blender',
            self::Consumption => 'restaurant',
        };
    }

    /**
     * Whether this movement brings goods in. Inflows recompute the weighted
     * average cost; outflows consume it.
     */
    public function isInflow(): bool
    {
        return in_array($this, [self::Purchase, self::ReturnIn, self::Production], true);
    }

    /**
     * Movements the user records by hand, as opposed to ones the system posts as
     * a side effect of a sale, a receipt or a count.
     */
    public function isManual(): bool
    {
        return in_array($this, [self::Adjustment, self::Waste, self::Transfer], true);
    }
}
