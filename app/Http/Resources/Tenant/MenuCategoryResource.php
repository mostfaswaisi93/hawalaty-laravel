<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\MenuCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'position' => $this->position,
        ];
    }
}
