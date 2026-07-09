<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['constant_list_id', 'value', 'position'])]
class ConstantListItem extends Model
{
    use HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function constantList(): BelongsTo
    {
        return $this->belongsTo(ConstantList::class);
    }
}
