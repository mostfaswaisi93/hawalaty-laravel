<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform-level audit trail of admin actions (license created, plan
 * changed, payment recorded...) - see BACKEND_PROMPT.md section 3. Fully
 * separate from `audit_log`, which lives inside each tenant's own database.
 */
#[Fillable(['admin_id', 'action', 'entity_type', 'entity_label'])]
class ActivityLog extends Model
{
    use HasUlids;

    /**
     * The table only has a `created_at` column, no `updated_at`.
     */
    const UPDATED_AT = null;

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
