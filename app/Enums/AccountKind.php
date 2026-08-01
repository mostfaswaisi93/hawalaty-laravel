<?php

namespace App\Enums;

enum AccountKind: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Wallet = 'wallet';

    public function label(): string
    {
        return match ($this) {
            self::Bank => __('بنك'),
            self::Wallet => __('محفظة'),
            self::Cash => __('نقدي'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Bank => 'account_balance',
            self::Wallet => 'account_balance_wallet',
            self::Cash => 'payments',
        };
    }
}
