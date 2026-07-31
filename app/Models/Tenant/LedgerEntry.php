<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement on an account's ledger: a مدين (debit) or دائن (credit), the
 * running balance after it, and a description. Append-only - created via
 * App\Services\Tenant\AccountService::postEntry (never edited), so there is no
 * updated_at (mirrors AuditLog). Phase 2b.
 */
#[Fillable(['account_id', 'debit', 'credit', 'running_balance', 'description', 'transfer_id'])]
class LedgerEntry extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'running_balance' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }
}
