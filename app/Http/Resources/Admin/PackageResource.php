<?php

namespace App\Http\Resources\Admin;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
