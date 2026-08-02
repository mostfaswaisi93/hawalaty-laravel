<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'kind', 'team_member_id', 'active'])]
class ServiceResource extends Model
{
    use HasFactory, HasUlids;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'bay' => __('مكان عمل'),
            'equipment' => __('معدّة'),
            default => __('فني'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function kinds(): array
    {
        return [
            'technician' => __('فني'),
            'bay' => __('مكان عمل'),
            'equipment' => __('معدّة'),
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
