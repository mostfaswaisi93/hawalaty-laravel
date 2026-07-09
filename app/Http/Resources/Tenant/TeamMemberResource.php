<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes App\Models\Tenant\TeamMember to match the Angular `TeamMember`
 * interface in D:\Angular\Hawalaty\src\app\data\team-members.ts —
 * `password` is never included (also #[Hidden] on the model itself). Adds
 * a denormalized `roleName` (mirrors ExpenseResource's `bankName`
 * convention). Controllers must eager-load `role` before returning this
 * resource. `tenantId` is intentionally omitted — no tenant_id column
 * exists anywhere in tenant space, see BACKEND_PROMPT.md section 4.
 *
 * @mixin \App\Models\Tenant\TeamMember
 */
class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roleId' => (string) $this->role_id,
            'roleName' => $this->role?->name,
            'active' => $this->active,
        ];
    }
}
