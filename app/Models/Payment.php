<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['license_id', 'amount', 'box_id'])]
class Payment extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }
}
