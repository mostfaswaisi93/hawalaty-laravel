<?php

namespace App\Models\Tenant;

use App\Enums\SerialStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'menu_item_id', 'serial', 'batch_id', 'status', 'location_id',
    'unit_cost', 'received_at', 'purchase_id',
    'sold_at', 'transfer_id', 'customer_id',
    'warranty_months', 'warranty_starts_at', 'warranty_ends_at', 'notes',
])]
class SerialNumber extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'status' => SerialStatus::class,
            'unit_cost' => 'decimal:3',
            'received_at' => 'datetime',
            'sold_at' => 'datetime',
            'warranty_starts_at' => 'date',
            'warranty_ends_at' => 'date',
            'warranty_months' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class)->withTrashed();
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'serial_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class, 'serial_number_id');
    }

    /**
     * Whether the promise made at the sale is still standing.
     *
     * A unit with no term, or one never sold, is not "expired" - it simply has
     * no warranty, and the two read differently to a customer at the counter.
     */
    public function underWarranty(): bool
    {
        return $this->warranty_ends_at !== null
            && ! $this->warranty_ends_at->isPast();
    }

    public function hasWarranty(): bool
    {
        return $this->warranty_ends_at !== null;
    }

    /**
     * Days of cover left. Negative once it has run out, null when there was
     * never any.
     */
    public function warrantyDaysLeft(): ?int
    {
        return $this->warranty_ends_at === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->warranty_ends_at->startOfDay(), false);
    }

    public function warrantyLabel(): string
    {
        return match (true) {
            ! $this->hasWarranty() => __('بلا ضمان'),
            $this->underWarranty() => __('ضمان ساري'),
            default => __('ضمان منتهٍ'),
        };
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', SerialStatus::InStock);
    }

    public function scopeForItem(Builder $query, string $itemId): Builder
    {
        return $query->where('menu_item_id', $itemId);
    }
}
