<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes App\Models\Tenant\ConstantList (+ eager-loaded `items`). See
 * ConstantListItemResource for why `items` is an array of
 * {id, value, position} objects rather than the frontend's current flat
 * `string[]` shape. Controllers must eager-load `items` before returning
 * this resource.
 *
 * @mixin \App\Models\Tenant\ConstantList
 */
class ConstantListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'key' => $this->key,
            'title' => $this->title,
            'items' => ConstantListItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
