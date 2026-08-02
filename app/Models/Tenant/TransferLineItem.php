<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['transfer_id', 'menu_item_id', 'name', 'quantity', 'price'])]
class TransferLineItem extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        // Three decimals on both, matching the columns: a line can be 1.350 kg
        // of produce at 6.475 a litre, and casting either back to two places
        // would round the receipt away from what the scale and the meter said.
        return [
            'quantity' => 'decimal:3',
            'price' => 'decimal:3',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(TransferLineItemModifier::class);
    }

    /**
     * Price of one unit including everything the customer chose.
     */
    public function unitPrice(): float
    {
        return (float) $this->price + (float) $this->modifiers->sum('price_delta');
    }

    public function lineTotal(): float
    {
        return round($this->unitPrice() * (float) $this->quantity, 2);
    }
}
