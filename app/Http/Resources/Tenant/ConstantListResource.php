<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
