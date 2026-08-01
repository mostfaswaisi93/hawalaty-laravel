<?php

namespace App\Models;

use App\Enums\BackupStatus;
use App\Enums\BackupTrigger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['license_id', 'size_mb', 'trigger', 'actor_name', 'status', 'file_path'])]
class Backup extends Model
{
    use HasUlids;

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
