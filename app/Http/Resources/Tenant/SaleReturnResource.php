<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'reference' => $this->reference,

            'customerName' => $this->customer_name,
            'customerPhone' => $this->customer_phone,

            'status' => $this->status,
            'statusLabel' => $this->statusLabel(),

            // The original sale is never deleted or amended - the return points
            // at it and both stay in the books.
            'transferId' => $this->transfer_id,
            'withoutReceipt' => $this->isWithoutReceipt(),

            'reason' => $this->reason,
            'notes' => $this->notes,

            'refundMethod' => $this->refund_method,
            'refundMethodLabel' => $this->refundMethodLabel(),

            'subtotal' => (float) $this->subtotal,
            'restockingFee' => (float) $this->restocking_fee,
            'refundTotal' => (float) $this->refund_total,

            'returnedAt' => $this->returned_at?->toIso8601String(),

            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => (string) $line->id,
                'itemId' => $line->menu_item_id,
                'name' => $line->name,
                'quantity' => (float) $line->quantity,
                'unitPrice' => (float) $line->unit_price,
                'lineTotal' => (float) $line->line_total,

                // Whether it goes back on the shelf, which in food and medicine
                // is not the shop's decision to make.
                'condition' => $line->condition,
                'resalable' => $line->isResalable(),
            ])),
        ];
    }
}
