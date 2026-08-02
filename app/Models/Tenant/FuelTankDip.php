<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fuel_shift_id', 'fuel_tank_id', 'opening_dip', 'closing_dip', 'deliveries'])]
class FuelTankDip extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'opening_dip' => 'decimal:3',
            'closing_dip' => 'decimal:3',
            'deliveries' => 'decimal:3',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(FuelShift::class, 'fuel_shift_id');
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class, 'fuel_tank_id');
    }

    /**
     * What the tank says left it: opening plus deliveries minus closing.
     */
    public function litresGone(): ?float
    {
        if ($this->closing_dip === null) {
            return null;
        }

        return round(
            (float) $this->opening_dip + (float) $this->deliveries - (float) $this->closing_dip,
            3,
        );
    }
}
