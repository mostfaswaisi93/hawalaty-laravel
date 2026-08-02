<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'transfer_id', 'customer_id', 'reference', 'customer_name', 'customer_phone',
    'status', 'reason', 'notes', 'refund_method', 'account_id',
    'subtotal', 'restocking_fee', 'refund_total', 'returned_at', 'team_member_id',
])]
class SaleReturn extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'restocking_fee' => 'decimal:2',
            'refund_total' => 'decimal:2',
            'returned_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleReturnLine::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'completed' => __('منفَّذ'),
            'rejected' => __('مرفوض'),
            default => __('مسودة'),
        };
    }

    public function refundMethodLabel(): string
    {
        return match ($this->refund_method) {
            'bank' => __('بنك أو محفظة'),
            'store_credit' => __('رصيد لدى المحل'),
            default => __('نقدًا'),
        };
    }

    /**
     * A return with no original sale behind it. Recorded rather than refused,
     * but worth seeing: a shop refunding things it cannot prove it sold has a
     * problem somewhere.
     */
    public function isWithoutReceipt(): bool
    {
        return $this->transfer_id === null;
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
}
