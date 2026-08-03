<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,

            'phone' => $this->phone,

            'orderCount' => $this->whenNotNull(
                isset($this->order_count) ? (int) $this->order_count : null
            ),
            'totalSpent' => $this->whenNotNull(
                isset($this->total_spent) ? (float) $this->total_spent : null
            ),
            'lastOrderAt' => $this->whenNotNull($this->last_order_at ?? null),

            'createdAt' => $this->created_at,
        ];
    }
}
