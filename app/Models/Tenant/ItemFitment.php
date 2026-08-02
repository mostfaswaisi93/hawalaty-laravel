<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['menu_item_id', 'vehicle_model_id', 'year_from', 'year_to', 'engine', 'notes'])]
class ItemFitment extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'year_from' => 'integer',
            'year_to' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    /**
     * Whether a given year falls inside this record.
     *
     * An open end is a real answer - "from 2015 onwards" - so a missing bound
     * matches rather than excludes.
     */
    public function coversYear(?int $year): bool
    {
        if ($year === null) {
            return true;
        }

        return ($this->year_from === null || $year >= $this->year_from)
            && ($this->year_to === null || $year <= $this->year_to);
    }

    public function yearLabel(): string
    {
        return match (true) {
            $this->year_from === null && $this->year_to === null => __('كل السنوات'),
            $this->year_to === null => __(':year فما فوق', ['year' => $this->year_from]),
            $this->year_from === null => __('حتى :year', ['year' => $this->year_to]),
            $this->year_from === $this->year_to => (string) $this->year_from,
            default => $this->year_from.' - '.$this->year_to,
        };
    }

    public function scopeCoveringYear(Builder $query, ?int $year): Builder
    {
        if ($year === null) {
            return $query;
        }

        return $query
            ->where(fn (Builder $q) => $q->whereNull('year_from')->orWhere('year_from', '<=', $year))
            ->where(fn (Builder $q) => $q->whereNull('year_to')->orWhere('year_to', '>=', $year));
    }
}
