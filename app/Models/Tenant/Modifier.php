<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'modifier_group_id', 'name', 'price_delta',
    'component_item_id', 'component_quantity', 'is_default', 'position', 'active',
])]
class Modifier extends Model
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
            'component_quantity' => 'decimal:4',
            'is_default' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'component_item_id');
    }

    /**
     * Whether choosing this takes something off the shelf as well as changing
     * the price. Most options do not - "no onion" costs nothing and consumes
     * nothing.
     */
    public function consumesStock(): bool
    {
        return $this->component_item_id !== null && (float) $this->component_quantity > 0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
