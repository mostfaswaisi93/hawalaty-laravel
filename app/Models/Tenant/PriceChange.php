<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['menu_item_id', 'old_price', 'new_price', 'cost_at_change', 'team_member_id'])]
class PriceChange extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:3',
            'new_price' => 'decimal:3',
            'cost_at_change' => 'decimal:3',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /**
     * How much the price moved, as a percentage of what it was. Null on the first
     * row, where there is nothing to compare against.
     */
    public function changePercent(): ?float
    {
        $old = (float) $this->old_price;

        if ($this->old_price === null || $old <= 0) {
            return null;
        }

        return round((((float) $this->new_price - $old) / $old) * 100, 1);
    }

    public function isIncrease(): bool
    {
        return (float) $this->new_price > (float) $this->old_price;
    }
}
