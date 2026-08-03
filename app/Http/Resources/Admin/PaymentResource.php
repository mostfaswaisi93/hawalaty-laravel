<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'licenseId' => (string) $this->license_id,
            'licenseCode' => $this->license?->license_code,
            'amount' => (float) $this->amount,
            'boxId' => (string) $this->box_id,
            'boxName' => $this->box?->box_name,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
