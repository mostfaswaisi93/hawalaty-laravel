<?php

namespace App\Models;

use App\Enums\BackupStatus;
use App\Enums\BackupTrigger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A backup run, either for a specific tenant's database (`license_id` set)
 * or for the platform database itself (`license_id` null) — see
 * BACKEND_PROMPT.md sections 3 and 6. All backups, across every tenant, are
 * managed from this one central table so the Super Admin panel has a
 * unified view.
 */
#[Fillable(['license_id', 'size_mb', 'trigger', 'actor_name', 'status', 'file_path'])]
class Backup extends Model
{
    use HasUlids;

    /**
     * The table only has a `created_at` column, no `updated_at`.
     */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'size_mb' => 'integer',
            'trigger' => BackupTrigger::class,
            'status' => BackupStatus::class,
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
