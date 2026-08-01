<?php

namespace App\Models\Tenant;

use App\Enums\PermissionKey;
use App\Enums\SalaryPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role_id', 'active', 'salary_amount', 'salary_period'])]
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
            'salary_amount' => 'decimal:2',
            'salary_period' => SalaryPeriod::class,
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

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function hasSalary(): bool
    {
        return $this->salary_amount !== null && $this->salary_period !== null;
    }

    public function hasPermission(PermissionKey|string $key): bool
    {
        $value = $key instanceof PermissionKey ? $key->value : $key;

        return $this->role?->permissions->contains('key', $value) ?? false;
    }
}
