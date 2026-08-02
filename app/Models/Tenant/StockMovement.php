<?php

namespace App\Models\Tenant;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the stock ledger. Append-only by contract.
 *
 * Nothing in the application updates or deletes a movement: a mistake is
 * corrected by posting its opposite, exactly as the financial ledger works. That
 * is what makes the history defensible and what makes offline sync safe.
 */
#[Fillable([
    'menu_item_id', 'location_id', 'batch_id', 'type', 'quantity', 'unit_cost',
    'batch_number', 'expires_at', 'serial_number',
    'reference_type', 'reference_id', 'reason', 'team_member_id',
])]
class StockMovement extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:3',
            'expires_at' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function scopeForItem(Builder $query, string $itemId): Builder
    {
        return $query->where('menu_item_id', $itemId);
    }

    public function scopeAtLocation(Builder $query, ?string $locationId): Builder
    {
        return $locationId ? $query->where('location_id', $locationId) : $query;
    }

    /**
     * Value this movement added to or removed from stock.
     */
    public function value(): float
    {
        return (float) $this->quantity * (float) $this->unit_cost;
    }
}
