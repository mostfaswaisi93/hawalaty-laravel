<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'reference' => $this->reference,

            'customerName' => $this->customer_name,
            'customerPhone' => $this->customer_phone,

            'subject' => $this->subject,
            'registration' => $this->registration,
            'odometer' => $this->odometer,
            'complaint' => $this->complaint,
            'diagnosis' => $this->diagnosis,

            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),

            'partsTotal' => (float) $this->parts_total,
            'labourTotal' => (float) $this->labour_total,
            'total' => (float) $this->total,

            // Nothing left the shelf and no money moved until this is set: the
            // work order bills through the ordinary till so stock and the ledger
            // keep exactly one path.
            'invoiced' => $this->isInvoiced(),
            'transferId' => $this->transfer_id,

            'receivedAt' => $this->received_at?->toIso8601String(),
            'deliveredAt' => $this->delivered_at?->toIso8601String(),

            'workmanship' => [
                'days' => $this->workmanship_warranty_days,
                'endsAt' => $this->workmanshipEndsAt()?->toDateString(),
                'covered' => $this->workmanshipStillCovered(),
            ],

            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => (string) $line->id,
                'itemId' => $line->menu_item_id,
                'name' => $line->name,
                'quantity' => (float) $line->quantity,
                'unitPrice' => (float) $line->unit_price,
                'lineTotal' => (float) $line->line_total,
            ])),
        ];
    }
}
