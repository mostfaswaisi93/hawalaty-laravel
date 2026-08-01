<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'active'])]
class CashBox extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function currencies(): MorphToMany
    {
        return $this->morphToMany(Currency::class, 'catalog', 'catalog_currency', 'catalog_id', 'currency_id');
    }
}
