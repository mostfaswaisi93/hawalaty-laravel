<?php

namespace App\Http\Resources\Admin;

use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'boxId' => (string) $this->box_id,
            'boxName' => $this->box?->box_name,
            'amount' => (float) $this->amount,
            'partnerId' => (string) $this->partner_id,
            'partnerName' => $this->partner?->name,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
