<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Booked = 'booked';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Booked => __('محجوز'),
            self::Confirmed => __('مؤكَّد'),
            self::InProgress => __('جارٍ'),
            self::Done => __('منتهٍ'),
            self::Cancelled => __('ملغى'),
            self::NoShow => __('لم يحضر'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Booked => 'bg-surface-container text-on-surface-variant',
            self::Confirmed => 'bg-secondary-container/40 text-secondary',
            self::InProgress => 'bg-primary/10 text-primary',
            self::Done => 'bg-success/10 text-success',
            self::Cancelled, self::NoShow => 'bg-error/10 text-error',
        };
    }

    /**
     * Whether this booking still occupies its slot.
     *
     * A cancellation frees the hour for someone else; a no-show does too, but
     * only after the fact - it is recorded so the diary tells the truth about
     * what was actually worked.
     */
    public function holdsSlot(): bool
    {
        return in_array($this, [self::Booked, self::Confirmed, self::InProgress], true);
    }
}
