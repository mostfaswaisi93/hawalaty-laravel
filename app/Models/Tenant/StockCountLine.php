<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_count_id', 'menu_item_id', 'expected_quantity', 'counted_quantity', 'unit_cost', 'batch_number'])]
class StockCountLine extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:3',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    /**
     * Counted minus expected. Null while the line is still uncounted - which is
     * not the same as a variance of zero, and the sheet has to show the
     * difference.
     */
    public function variance(): ?float
    {
        if ($this->counted_quantity === null) {
            return null;
        }

        return (float) $this->counted_quantity - (float) $this->expected_quantity;
    }
}
