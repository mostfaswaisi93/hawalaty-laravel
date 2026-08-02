<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'menu_item_id', 'location_id', 'quantity', 'status', 'produced_at',
    'unit_cost', 'batch_number', 'expires_at', 'notes', 'team_member_id',
])]
class ProductionRun extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:3',
            'produced_at' => 'datetime',
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

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'posted' => __('مُرحَّل'),
            'cancelled' => __('ملغى'),
            default => __('مسودة'),
        };
    }

    public function totalCost(): float
    {
        return (float) $this->quantity * (float) $this->unit_cost;
    }
}
