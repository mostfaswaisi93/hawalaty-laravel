<?php

namespace App\Models\Tenant;

use App\Enums\WarrantyClaimStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'serial_number_id', 'menu_item_id', 'transfer_id', 'customer_id',
    'reference', 'customer_name', 'customer_phone', 'issue', 'status',
    'under_warranty', 'repair_cost', 'charged_amount',
    'resolution', 'reported_at', 'resolved_at', 'team_member_id',
])]
class WarrantyClaim extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'status' => WarrantyClaimStatus::class,
            'under_warranty' => 'boolean',
            'repair_cost' => 'decimal:2',
            'charged_amount' => 'decimal:2',
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(SerialNumber::class, 'serial_number_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /**
     * What the shop absorbed: the repair cost it did not pass on.
     */
    public function absorbedCost(): float
    {
        return round((float) $this->repair_cost - (float) $this->charged_amount, 2);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            WarrantyClaimStatus::Open->value,
            WarrantyClaimStatus::InRepair->value,
        ]);
    }
}
