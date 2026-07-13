<?php

namespace App\Notifications\Tenant;

use App\Models\Tenant\Transfer;
use Illuminate\Notifications\Notification;

/**
 * Fired when an order's status changes to completed or failed
 * (TransferService::updateStatus) — never for pending, since that's the
 * default at creation, not a meaningful transition. Database channel only,
 * sent synchronously.
 */
class OrderStatusChangedNotification extends Notification
{
    public function __construct(private readonly Transfer $transfer) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'order_status_changed',
            'transferId' => $this->transfer->id,
            'customer' => $this->transfer->customer,
            'amount' => (float) $this->transfer->amount,
            'status' => $this->transfer->status->value,
        ];
    }
}
