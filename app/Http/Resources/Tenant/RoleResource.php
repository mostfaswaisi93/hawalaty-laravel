<?php

namespace App\Http\Resources\Tenant;

use App\Enums\PermissionKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'key' => $this->key?->value,
            'name' => $this->name,
            'isSystem' => $this->is_system,
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions
                    ->pluck('key')
                    ->map(fn (PermissionKey $key) => $key->value)
                    ->values(),
                [],
            ),
        ];
    }
}
