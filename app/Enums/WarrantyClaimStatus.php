<?php

namespace App\Enums;

enum WarrantyClaimStatus: string
{
    case Open = 'open';
    case InRepair = 'in_repair';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
    case Replaced = 'replaced';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('مفتوحة'),
            self::InRepair => __('قيد الإصلاح'),
            self::Resolved => __('مُنجَزة'),
            self::Rejected => __('مرفوضة'),
            self::Replaced => __('استُبدلت'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Open => 'pending',
            self::InRepair => 'build',
            self::Resolved => 'task_alt',
            self::Rejected => 'block',
            self::Replaced => 'swap_horiz',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'bg-secondary-container/40 text-secondary',
            self::InRepair => 'bg-primary/10 text-primary',
            self::Resolved, self::Replaced => 'bg-success/10 text-success',
            self::Rejected => 'bg-error/10 text-error',
        };
    }

    /**
     * Whether the claim is still someone's job today.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::InRepair], true);
    }
}
