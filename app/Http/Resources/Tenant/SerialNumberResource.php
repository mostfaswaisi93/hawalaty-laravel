<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SerialNumberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'serial' => $this->serial,

            'item' => [
                'id' => (string) $this->menu_item_id,
                'name' => $this->item?->name,
            ],

            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),

            'batchNumber' => $this->batch?->batch_number,
            'location' => $this->location?->name,

            'soldAt' => $this->sold_at?->toIso8601String(),
            'customer' => $this->customer?->name,

            'warranty' => [
                'months' => $this->warranty_months,
                'startsAt' => $this->warranty_starts_at?->toDateString(),
                'endsAt' => $this->warranty_ends_at?->toDateString(),

                // The answer the counter actually needs when someone walks in
                // holding the thing.
                'active' => $this->underWarranty(),
                'daysLeft' => $this->warrantyDaysLeft(),
                'label' => $this->warrantyLabel(),
            ],
        ];
    }
}
