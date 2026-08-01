<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\TeamMember;

class AuditLogger
{
    public static function record(
        AuditAction $action,
        AuditEntityType $entityType,
        string $entityLabel,
        AuditCategory $category,
    ): void {
        $actor = auth('tenant')->user() ?? auth('sanctum')->user();

        $actorName = 'System';
        $actorEmail = 'system@hawalaty.local';

        if ($actor instanceof TeamMember) {
            $actorName = $actor->name;
            $actorEmail = $actor->email;
        }

        AuditLog::create([
            'actor_name' => $actorName,
            'actor_email' => $actorEmail,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_label' => $entityLabel,
            'category' => $category,
        ]);
    }
}
