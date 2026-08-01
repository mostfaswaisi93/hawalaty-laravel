<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'period_from',
    'period_to',
    'total_payments',
    'total_expenses',
    'distributable_profit',
    'total_entitlement',
    'total_drawn',
    'total_net',
    'retained',
    'status',
    'admin_id',
    'notes',
    'confirmed_at',
])]
class Settlement extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'total_payments' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'distributable_profit' => 'decimal:2',
            'total_entitlement' => 'decimal:2',
            'total_drawn' => 'decimal:2',
            'total_net' => 'decimal:2',
            'retained' => 'decimal:2',
            'status' => SettlementStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SettlementLine::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === SettlementStatus::Confirmed;
    }
}
