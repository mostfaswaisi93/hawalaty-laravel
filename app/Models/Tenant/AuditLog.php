<?php

namespace App\Models\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only log — no `updated_at` column exists at all, so UPDATED_AT is
 * disabled entirely rather than pointed at a nonexistent column. Distinct
 * from the platform's own `activity_logs` table.
 */
#[Fillable(['actor_name', 'actor_email', 'action', 'entity_type', 'entity_label', 'category'])]
class AuditLog extends Model
{
    use HasUlids;

    protected $table = 'audit_log';

    const UPDATED_AT = null;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'entity_type' => AuditEntityType::class,
            'category' => AuditCategory::class,
            'created_at' => 'datetime',
        ];
    }
}
