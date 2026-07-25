<?php

namespace App\Models\Tenant;

use App\Enums\PermissionKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The real "User"/login model of tenant space - see BACKEND_PROMPT.md
 * sections 4-5 and D:\Angular\Hawalaty\src\app\data\team-members.ts.
 */
#[Fillable(['name', 'email', 'password', 'role_id', 'active'])]
#[Hidden(['password', 'remember_token'])]
class TeamMember extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Named hasPermission() rather than can() because TeamMember extends
     * Illuminate\Foundation\Auth\User, which already defines can() via the
     * Authorizable trait for Gate/Policy checks - a same-named method here
     * would silently shadow it (a mistake made and caught in the earlier
     * prototype at D:\Angular\Hawalaty_Laravel).
     */
    public function hasPermission(PermissionKey|string $key): bool
    {
        $value = $key instanceof PermissionKey ? $key->value : $key;

        return $this->role?->permissions->contains('key', $value) ?? false;
    }
}
