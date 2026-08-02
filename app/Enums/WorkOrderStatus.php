<?php

namespace App\Enums;

/**
 * The life of a job, from the car arriving to the customer driving away.
 */
enum WorkOrderStatus: string
{
    case Received = 'received';
    case Diagnosing = 'diagnosing';
    case Quoted = 'quoted';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => __('مستلَم'),
            self::Diagnosing => __('قيد الفحص'),
            self::Quoted => __('عرض سعر'),
            self::Approved => __('موافَق عليه'),
            self::InProgress => __('قيد التنفيذ'),
            self::Done => __('جاهز'),
            self::Delivered => __('سُلِّم'),
            self::Cancelled => __('ملغى'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Received => 'inbox',
            self::Diagnosing => 'search',
            self::Quoted => 'request_quote',
            self::Approved => 'thumb_up',
            self::InProgress => 'build',
            self::Done => 'task_alt',
            self::Delivered => 'local_shipping',
            self::Cancelled => 'block',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Received, self::Diagnosing => 'bg-surface-container text-on-surface-variant',
            self::Quoted, self::Approved => 'bg-secondary-container/40 text-secondary',
            self::InProgress => 'bg-primary/10 text-primary',
            self::Done, self::Delivered => 'bg-success/10 text-success',
            self::Cancelled => 'bg-error/10 text-error',
        };
    }

    /**
     * Whether the job is still on the floor.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Delivered, self::Cancelled], true);
    }

    /**
     * Whether its lines may still change. Once billed they cannot: the invoice
     * has already moved stock and money against them.
     */
    public function isEditable(): bool
    {
        return $this !== self::Delivered && $this !== self::Cancelled;
    }
}
