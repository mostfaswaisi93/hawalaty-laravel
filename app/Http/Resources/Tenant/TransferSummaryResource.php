<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'customer' => $this->customer,
            'date' => $this->created_at?->toDateString(),
            'status' => $this->status?->value,
            'amount' => (float) $this->amount,
        ];
    }
}
