<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->box_name,
            'balance' => (float) $this->box_balance,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
