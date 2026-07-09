<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes App\Models\Tenant\Device to match the Angular `Device` interface in
 * D:\Angular\Hawalaty\src\app\data\devices.ts, with deliberate deviations:
 *   - `icon` maps from the `platform_icon` column.
 *   - `lastActiveAt` (a real ISO timestamp) replaces the mock's
 *     `lastActiveKey` translation-key field — the backend tracks a real
 *     timestamp, not a canned "just now / hours ago" bucket.
 *   - `locationKey` has no backend equivalent and is intentionally omitted.
 *
 * DeviceService must attach a transient `is_current` attribute before this
 * resource is built — it is NOT a real database column, see
 * Device::isCurrent().
 *
 * @mixin \App\Models\Tenant\Device
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
