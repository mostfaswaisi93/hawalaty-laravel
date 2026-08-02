<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where one variant sits on one axis: this shirt is the medium, and the red.
 *
 * Composite key, no timestamps - the row is the coordinate and there is nothing
 * else to record about it.
 */
#[Fillable(['menu_item_id', 'constant_list_id', 'constant_list_item_id'])]
class MenuItemVariantValue extends Model
{
    protected $table = 'menu_item_variant_values';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function axis(): BelongsTo
    {
        return $this->belongsTo(ConstantList::class, 'constant_list_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ConstantListItem::class, 'constant_list_item_id');
    }
}
