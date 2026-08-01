<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ConstantListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstantListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'value' => $this->value,
            'position' => $this->position,
        ];
    }
}
