<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'kind', 'is_default', 'active'])]
class Location extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    public function getConnectionName(): string
    {
        return 'tenant';
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * The location a movement lands in when the user was never asked.
     *
     * Most tenants have exactly one and never open this screen, so falling back
     * to the oldest row keeps single-site workspaces working even if the default
     * flag was cleared.
     */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->active()->oldest()->first();
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'warehouse' => __('مستودع'),
            'fridge' => __('ثلاجة'),
            'vehicle' => __('مركبة توزيع'),
            default => __('محل'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function kinds(): array
    {
        return [
            'store' => __('محل'),
            'warehouse' => __('مستودع'),
            'fridge' => __('ثلاجة'),
            'vehicle' => __('مركبة توزيع'),
        ];
    }
}
