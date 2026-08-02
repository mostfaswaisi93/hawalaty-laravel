<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'supplier_id', 'location_id', 'reference', 'invoice_number', 'status',
    'ordered_at', 'received_at', 'subtotal', 'tax_total', 'other_costs', 'total', 'notes',
])]
class Purchase extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'ordered_at' => 'date',
            'received_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'other_costs' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLineItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', 'received');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'received' => __('مستلمة'),
            'cancelled' => __('ملغاة'),
            default => __('مسودة'),
        };
    }
}
