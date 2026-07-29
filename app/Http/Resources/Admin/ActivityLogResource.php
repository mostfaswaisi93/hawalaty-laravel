<?php

namespace App\Http\Resources\Admin;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Controllers must eager-load `admin` before returning this resource.
 *
 * @mixin ActivityLog
 */
class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'adminId' => $this->admin_id ? (string) $this->admin_id : null,
            'adminName' => $this->admin?->name,
            'action' => $this->action,
            'entityType' => $this->entity_type,
            'entityLabel' => $this->entity_label,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
