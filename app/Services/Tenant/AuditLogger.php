<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\TeamMember;

/**
 * Small stateless helper that writes a single append-only `audit_log` row
 * inside the current tenant database. Unlike the superseded prototype's
 * version (App\Services\AuditLogger there), no `tenant_id` is stamped -
 * physical DB-per-tenant isolation already scopes every row, see
 * BACKEND_PROMPT.md section 4. Called explicitly from each domain Service
 * after a successful write, rather than via Eloquent model-event hooks -
 * keeps all business logic (including "what counts as auditable") in the
 * Service layer per this task's architecture requirement.
 */
class AuditLogger
{
    public static function record(
        AuditAction $action,
        AuditEntityType $entityType,
        string $entityLabel,
        AuditCategory $category,
    ): void {
        // The web app authenticates via the session-based `tenant` guard;
        // Sanctum only covers API/mobile clients. Checking `tenant` first
        // means the real actor is captured for every Blade-driven action -
        // `sanctum` remains the fallback so API-originated writes still
        // attribute correctly, with "System" reserved for truly unauthenticated
        // contexts (console commands, queued jobs).
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
