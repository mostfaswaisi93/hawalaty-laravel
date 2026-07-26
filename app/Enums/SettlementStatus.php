<?php

namespace App\Enums;

enum SettlementStatus: string
{
    /** Computed and saved, but the period is not closed yet - still editable. */
    case Draft = 'draft';

    /** Closed. The period is locked and the next run starts after it. */
    case Confirmed = 'confirmed';
}
