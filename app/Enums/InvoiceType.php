<?php

namespace App\Enums;

/**
 * Invoice type. The UNCL 1001 code is what goes on the wire.
 */
enum InvoiceType: string
{
    case Standard = 'standard';
    case Simplified = 'simplified';
    case CreditNote = 'credit_note';

    /**
     * UNCL 1001: 388 is a commercial invoice, 381 a credit note.
     */
    public function documentTypeCode(): string
    {
        return $this === self::CreditNote ? '381' : '388';
    }

    /**
     * The sub-type regimes in this region use to separate business-to-business
     * invoices, which need the buyer's tax number, from over-the-counter ones,
     * which do not.
     */
    public function isSimplified(): bool
    {
        return $this === self::Simplified;
    }

    public function label(): string
    {
        return match ($this) {
            self::Standard => __('فاتورة ضريبية'),
            self::Simplified => __('فاتورة مبسّطة'),
            self::CreditNote => __('إشعار دائن'),
        };
    }

    public function seriesPrefix(): string
    {
        return $this === self::CreditNote ? 'CN' : 'INV';
    }
}
