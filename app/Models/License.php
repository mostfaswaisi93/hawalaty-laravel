<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A customer's license to run their own tenant database - see
 * BACKEND_PROMPT.md sections 3, 5 and 8.1. The actual connection details for
 * the tenant database live on the related DatabaseConnection model, not here.
 */
#[Fillable(['license_code', 'customer_name', 'package_id', 'status', 'expired_at', 'customer_balance'])]
class License extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'expired_at' => 'date',
            'customer_balance' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
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
