<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
