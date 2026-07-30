<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes App\Models\Tenant\AuditLog to match the Angular `AuditLogEntry`
 * interface in D:\Angular\Hawalaty\src\app\data\audit-log.ts. Note the
 * mock's field is named `timestamp` (not `createdAt`) even though it maps
 * to the `created_at` column. `tenantId` is intentionally omitted (no such
 * column exists in tenant space).
 *
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'actorName' => $this->actor_name,
            'actorEmail' => $this->actor_email,
            'action' => $this->action->value,
            'entityType' => $this->entity_type->value,
            'entityLabel' => $this->entity_label,
            'category' => $this->category->value,
            'timestamp' => $this->created_at?->toISOString(),
        ];
    }
}
