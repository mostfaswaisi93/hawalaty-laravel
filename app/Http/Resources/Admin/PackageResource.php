<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `features`/`deviceLimit` are exposed read/write here so a future task can
 * build live device-limit / feature-flag enforcement on top - see
 * BACKEND_PROMPT.md section 11's explicitly-unresolved note on this.
 *
 * @mixin \App\Models\Package
 */
class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'price' => (float) $this->price,
            'deviceLimit' => (int) $this->device_limit,
            'features' => $this->features ?? [],
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
