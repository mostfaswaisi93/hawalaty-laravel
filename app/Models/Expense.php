<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The platform's OWN operating expenses (hosting, domain, SSL...) — see
 * BACKEND_PROMPT.md section 3. This is NOT any tenant's expenses; a
 * tenant-side `Expense` model lives in a separate per-license database and
 * will be added by a later task with its own connection context.
 */
#[Fillable(['box_id', 'description', 'amount', 'date'])]
class Expense extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }
}
