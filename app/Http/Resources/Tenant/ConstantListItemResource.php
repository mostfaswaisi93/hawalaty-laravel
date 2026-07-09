<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * App\Models\Tenant\ConstantListItem has no direct Angular `interface`
 * counterpart — the current frontend's `CustomConstantList.items` is a flat
 * `string[]` (src/app/core/constants.service.ts) with no per-item id. Since
 * DELETE /api/v1/constant-lists/{list}/items/{item} requires an id to
 * target a specific item, this resource deliberately exposes `id` alongside
 * `value` rather than matching the frontend's flat-string shape.
 *
 * @mixin \App\Models\Tenant\ConstantListItem
 */
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
