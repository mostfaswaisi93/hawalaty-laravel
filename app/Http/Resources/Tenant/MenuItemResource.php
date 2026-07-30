<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes App\Models\Tenant\MenuItem to match the Angular `MenuItem`
 * interface in D:\Angular\Hawalaty\src\app\data\menu.ts exactly: id, name,
 * category (flat string), price, active. `category` is resolved from the
 * menuCategory relation's name. Controllers must eager-load `menuCategory`
 * before returning this resource.
 *
 * @mixin MenuItem
 */
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
