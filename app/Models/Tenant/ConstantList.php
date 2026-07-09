<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'title'])]
class ConstantList extends Model
{
    use HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConstantListItem::class);
    }
}
