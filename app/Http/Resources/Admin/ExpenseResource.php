<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'boxId' => (string) $this->box_id,
            'boxName' => $this->box?->box_name,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'date' => $this->date?->toDateString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
