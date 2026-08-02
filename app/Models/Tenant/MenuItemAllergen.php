<?php

namespace App\Models\Tenant;

use App\Enums\Allergen;
use App\Enums\AllergenPresence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One allergen declared on one item.
 *
 * Composite primary key, no id and no timestamps: the row is the fact, and there
 * is nothing else to say about it.
 */
#[Fillable(['menu_item_id', 'allergen', 'presence'])]
class MenuItemAllergen extends Model
{
    protected $table = 'menu_item_allergens';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'allergen' => Allergen::class,
            'presence' => AllergenPresence::class,
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
