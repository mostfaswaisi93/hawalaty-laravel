<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes Device for the client:
 *   - `icon` maps from the `platform_icon` column.
 *   - `lastActiveAt` is a real ISO timestamp, not a bucketed label.
 *
 * DeviceService MUST attach a transient `is_current` attribute before this is
 * built - it is not a database column. See Device::isCurrent().
 *
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'browser' => $this->browser,
            'icon' => $this->platform_icon,
            'lastActiveAt' => $this->last_active_at?->toISOString(),
            'isCurrent' => (bool) ($this->is_current ?? false),
        ];
    }
}
