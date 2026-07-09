<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant's OWN business expenses — NOT the platform's own operating
 * expenses (App\Models\Expense lives in the platform DB). Namespaced under
 * App\Models\Tenant to avoid a class-name collision with that model, and by
 * extension every tenant-space domain model lives under this same
 * App\Models\Tenant\ sub-namespace for consistency. See BACKEND_PROMPT.md
 * section 4 and D:\Angular\Hawalaty\src\app\data\expenses.ts.
 */
#[Fillable(['description', 'amount', 'bank_id', 'date'])]
class Expense extends Model
{
    use HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
