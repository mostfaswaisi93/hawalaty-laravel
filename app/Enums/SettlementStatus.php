<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case Draft = 'draft';

    case Confirmed = 'confirmed';
}
