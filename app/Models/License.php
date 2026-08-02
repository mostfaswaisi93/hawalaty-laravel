<?php

namespace App\Models;

use App\Enums\BusinessCapability;
use App\Enums\BusinessType;
use App\Enums\LicenseStatus;
use App\Enums\PackageFeature;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['license_code', 'customer_name', 'address', 'phone', 'business_type', 'package_id', 'status', 'expired_at', 'customer_balance'])]
class License extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'business_type' => BusinessType::class,
            'expired_at' => 'date',
            'customer_balance' => 'decimal:2',
        ];
    }

    public function businessType(): BusinessType
    {
        return $this->business_type ?? BusinessType::default();
    }

    /**
     * Whether the license's vertical switches this module on.
     *
     * Distinct from hasFeature(): a package feature is something the operator
     * sells, a capability is something the trade requires.
     */
    public function hasCapability(BusinessCapability|string $capability): bool
    {
        $case = $capability instanceof BusinessCapability
            ? $capability
            : BusinessCapability::tryFrom($capability);

        return $case instanceof BusinessCapability
            && $this->businessType()->hasCapability($case);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function hasFeature(PackageFeature|string $feature): bool
    {
        $key = $feature instanceof PackageFeature ? $feature->value : $feature;

        return in_array($key, (array) ($this->package?->features ?? []), true);
    }

    public function databaseConnection(): HasOne
    {
        return $this->hasOne(DatabaseConnection::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }
}
