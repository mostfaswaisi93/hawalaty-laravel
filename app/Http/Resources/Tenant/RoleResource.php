<?php

namespace App\Http\Resources\Tenant;

use App\Enums\PermissionKey;
use App\Models\Tenant\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes App\Models\Tenant\Role to match the Angular `Role` interface in
 * D:\Angular\Hawalaty\src\app\data\roles.ts - `permissions` is flattened to
 * a plain array of PermissionKey strings, not nested permission objects.
 * Controllers must eager-load `permissions` before returning this
 * resource. `tenantId` is intentionally omitted (no such column exists).
 *
 * @mixin Role
 */
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
