<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,

            'service' => [
                'id' => (string) $this->menu_item_id,
                'name' => $this->item?->name,
            ],
            'resource' => [
                'id' => (string) $this->service_resource_id,
                'name' => $this->resource?->name,
            ],

            'customerName' => $this->customer_name,
            'customerPhone' => $this->customer_phone,

            'startsAt' => $this->starts_at?->toIso8601String(),
            'endsAt' => $this->ends_at?->toIso8601String(),
            'minutes' => $this->minutes(),

            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),

            // Taken against the booking, and revenue only once the work is done -
            // IFRS 15 does not let a deposit be income before then.
            'deposit' => (float) $this->deposit,

            'notes' => $this->notes,
        ];
    }
}
