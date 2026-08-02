<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarrantyClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'reference' => $this->reference,

            'item' => [
                'id' => (string) $this->menu_item_id,
                'name' => $this->item?->name,
            ],
            'serial' => $this->serialNumber?->serial,

            'customerName' => $this->customer_name,
            'customerPhone' => $this->customer_phone,
            'issue' => $this->issue,

            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),

            // Decided when the claim was opened and frozen: whether a unit was
            // covered is a fact about the day it was brought in, and it must not
            // change later because the clock ran out in the meantime.
            'underWarranty' => (bool) $this->under_warranty,

            'repairCost' => (float) $this->repair_cost,
            'chargedAmount' => (float) $this->charged_amount,
            'absorbedCost' => $this->absorbedCost(),

            'resolution' => $this->resolution,
            'reportedAt' => $this->reported_at?->toIso8601String(),
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
