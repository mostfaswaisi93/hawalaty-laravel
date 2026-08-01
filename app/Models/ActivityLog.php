<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admin_id', 'action', 'entity_type', 'entity_label'])]
class ActivityLog extends Model
{
    use HasUlids;

    const UPDATED_AT = null;

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
