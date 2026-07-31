<?php

namespace App\Enums;

/**
 * Where an Account holds its money. Drives which set of fields it carries:
 *   bank   -> bank name + account type + IBAN
 *   wallet -> wallet name + wallet type + wallet phone
 *   cash   -> a cash box, and nothing else (a drawer has no number or IBAN)
 *
 * These three are the choices offered wherever money moves - the order form,
 * the expense form and payroll.
 */
/*
 * Declaration order IS the on-screen order everywhere cases() drives a chooser
 * (نقدي، بنك، محفظة) - cash first, because it is the commonest.
 */
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
