<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A choice the customer made, frozen onto the order line.
 *
 * Name and price are copied rather than joined: a receipt has to keep saying
 * what was actually sold and charged, even after the modifier is renamed,
 * repriced or taken off the menu.
 */
#[Fillable(['transfer_line_item_id', 'modifier_id', 'name', 'price_delta'])]
class TransferLineItemModifier extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:3',
        ];
    }

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(TransferLineItem::class, 'transfer_line_item_id');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }
}
