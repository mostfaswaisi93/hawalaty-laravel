<?php

namespace App\Models\Tenant;

use App\Enums\PermissionKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Lives inside every tenant database (physical DB-per-tenant) - the 7 fixed
 * rows from App\Enums\PermissionKey are seeded here at provisioning time.
 * See BACKEND_PROMPT.md section 4.
 */
#[Fillable(['key'])]
class Permission extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'key' => PermissionKey::class,
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
