<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fuel_shift_id', 'fuel_pump_id', 'opening_reading', 'closing_reading', 'test_litres', 'unit_price'])]
class FuelMeterReading extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'opening_reading' => 'decimal:3',
            'closing_reading' => 'decimal:3',
            'test_litres' => 'decimal:3',
            'unit_price' => 'decimal:3',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(FuelShift::class, 'fuel_shift_id');
    }

    public function pump(): BelongsTo
    {
        return $this->belongsTo(FuelPump::class, 'fuel_pump_id');
    }

    /**
     * Litres that actually left as sales.
     *
     * Test litres are metered like any other delivery but go back into the tank,
     * so leaving them in would overstate sales and understate stock by the same
     * amount - a variance out of nothing.
     */
    public function litresSold(): float
    {
        if ($this->closing_reading === null) {
            return 0.0;
        }

        return max(
            round((float) $this->closing_reading - (float) $this->opening_reading - (float) $this->test_litres, 3),
            0.0,
        );
    }

    public function value(): float
    {
        return round($this->litresSold() * (float) $this->unit_price, 2);
    }
}
