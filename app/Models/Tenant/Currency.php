<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A currency an account holds money in. `symbol` (₪ / $ / €) is what amounts
 * are formatted with once the app goes multi-currency. Reference catalog -
 * see App\Services\Tenant\CatalogRegistry.
 */
#[Fillable(['name', 'symbol', 'active'])]
class Currency extends Model
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
}
