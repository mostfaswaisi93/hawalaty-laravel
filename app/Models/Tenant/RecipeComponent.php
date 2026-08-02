<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['parent_item_id', 'component_item_id', 'quantity', 'waste_percent', 'position'])]
class RecipeComponent extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'waste_percent' => 'decimal:2',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_item_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'component_item_id');
    }

    /**
     * Quantity actually drawn from the shelf for one unit of the parent.
     *
     * The recipe states what ends up in the dish; the yield loss is what the
     * kitchen throws away getting it there. Costing and depletion both have to
     * use the larger figure or the shelf will disagree with the system.
     */
    public function grossQuantity(): float
    {
        $waste = (float) $this->waste_percent;

        if ($waste <= 0 || $waste >= 100) {
            return (float) $this->quantity;
        }

        return (float) $this->quantity / (1 - ($waste / 100));
    }
}
