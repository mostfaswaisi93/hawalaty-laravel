<?php

namespace App\Enums;

enum SalaryPeriod: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => __('يومي'),
            self::Monthly => __('شهري'),
        };
    }
}
