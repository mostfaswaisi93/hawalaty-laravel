<?php

namespace App\Models\Tenant;

use App\Enums\SyncOperation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The offline-sync engine's event log. Deliberately does NOT use HasUlids:
 * the primary key is `server_sequence`, an auto-incrementing integer that
 * doubles as the strictly-ordered pull cursor - see that migration's doc
 * comment and BACKEND_PROMPT.md sections 9-10.
 */
#[Fillable(['device_id', 'client_uuid', 'entity_type', 'entity_id', 'operation', 'payload'])]
class SyncEvent extends Model
{
    protected $primaryKey = 'server_sequence';

    public $incrementing = true;

    protected $keyType = 'int';

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'operation' => SyncOperation::class,
            'payload' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
