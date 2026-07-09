<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product catalog entry — see BACKEND_PROMPT.md section 3. Licenses reference
 * a package to determine device limits and feature flags.
 */
#[Fillable(['name', 'price', 'device_limit', 'features'])]
class Package extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'device_limit' => 'integer',
            'features' => 'array',
        ];
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }
}
