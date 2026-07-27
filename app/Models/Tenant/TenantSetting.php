<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A single per-tenant configuration value. Read through
 * App\Services\Tenant\CatalogNaming (and future readers) rather than directly,
 * so defaults and caching live in one place.
 */
#[Fillable(['key', 'value'])]
class TenantSetting extends Model
{
    use HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }
}
