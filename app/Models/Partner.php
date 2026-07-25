<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A project partner and their profit-share percentage - see
 * BACKEND_PROMPT.md section 3.
 */
#[Fillable(['name', 'percentage'])]
class Partner extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
        ];
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }
}
