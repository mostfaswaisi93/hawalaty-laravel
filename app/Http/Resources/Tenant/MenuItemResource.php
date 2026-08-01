<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'category' => $this->menuCategory?->name,
            'price' => (float) $this->price,
            'active' => $this->active,
        ];
    }
}
