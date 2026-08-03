<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferLineItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'itemId' => $this->menu_item_id !== null ? (string) $this->menu_item_id : null,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => (float) $this->price,
        ];
    }
}
