<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One partner's line inside a clearing run. `partner_name`/`percentage` are
 * snapshots taken when the run was created - see the migration for why.
 */
#[Fillable([
    'settlement_id',
    'partner_id',
    'partner_name',
    'percentage',
    'entitlement',
    'drawn',
    'net',
])]
class SettlementLine extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'entitlement' => 'decimal:2',
            'drawn' => 'decimal:2',
            'net' => 'decimal:2',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
