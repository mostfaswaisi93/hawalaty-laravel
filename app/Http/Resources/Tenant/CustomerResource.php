<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Tenant\Customer
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // The customer's identity - `customers.phone` is UNIQUE and is what
            // findOrCreate matches on, not the name.
            'phone' => $this->phone,

            // Present only when the query asked for them (list() and find() add
            // these as SQL aggregates); omitted rather than sent as null so a
            // client can tell "no orders" from "not requested".
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
