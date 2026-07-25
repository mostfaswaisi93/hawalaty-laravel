<?php

namespace App\Notifications\Tenant;

use App\Models\Tenant\Transfer;
use Illuminate\Notifications\Notification;

/**
 * Fired when a new order is created (TransferService::create), so team
 * members on shift see it without refreshing the dashboard. Database
 * channel only - sent synchronously (no ShouldQueue), matching the rest of
 * this app's synchronous architecture; no queue worker is assumed running.
 */
class NewOrderNotification extends Notification
{
    public function __construct(private readonly Transfer $transfer) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'new_order',
            'transferId' => $this->transfer->id,
            'customer' => $this->transfer->customer,
            'amount' => (float) $this->transfer->amount,
            'itemName' => $this->transfer->item_name,
        ];
    }
}
